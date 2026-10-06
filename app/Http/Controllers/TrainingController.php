<?php

namespace App\Http\Controllers;

use App\Models\Divisi;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\TrainingQuizResult;
use App\Models\User;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TrainingController extends Controller
{
    /**
     * Display a listing of trainings
     */
    public function index()
    {
        $trainings = Training::with(['trainer', 'participants.user', 'quizResults'])
            ->filter()
            ->latest('training_date')
            ->paginate(15);

        $stats = [
            'total' => Training::count(),
            'scheduled' => Training::where('status', 'scheduled')->count(),
            'ongoing' => Training::where('status', 'ongoing')->count(),
            'completed' => Training::where('status', 'completed')->count(),
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
    public function create()
    {
        $users = User::orderBy('full_name')->get();
        $divisis = Divisi::with('users')->orderBy('name')->get();

        return view('training.create', [
            'title' => 'Buat Jadwal Pelatihan',
            'active' => 'training',
            'users' => $users,
            'divisis' => $divisis,
        ]);
    }

    /**
     * Store a newly created training
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'trainer_id' => 'required|exists:users,id',
            'description' => 'nullable|string',
            'training_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'zoom_link' => 'required|string',
            'participants' => 'required|array|min:1',
            'participants.*' => 'exists:users,id',
            'send_wa_now' => 'nullable|boolean',
            'quiz_mode' => 'nullable|in:formal,game',
            'is_attendance_active' => 'nullable|boolean',
            'require_attendance_proof' => 'nullable|boolean',
        ]);

        $training = Training::create([
            'title' => $validated['title'],
            'trainer_id' => $validated['trainer_id'],
            'description' => $validated['description'] ?? null,
            'training_date' => $validated['training_date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'zoom_link' => $validated['zoom_link'],
            'status' => 'scheduled',
            'is_quiz_active' => false,
            'is_attendance_active' => $request->boolean('is_attendance_active', false),
            'require_attendance_proof' => $request->boolean('require_attendance_proof', false),
            'quiz_mode' => $request->input('quiz_mode', 'formal'),
        ]);

        // Add participants
        $participantsData = [];
        $uniqueParticipants = array_unique($validated['participants']);

        foreach ($uniqueParticipants as $userId) {
            $participant = TrainingParticipant::create([
                'training_id' => $training->id,
                'user_id' => $userId,
                'token' => Str::random(40) . '_' . time(),
                'attendance_status' => 'pending',
            ]);
            $participantsData[] = $participant;
        }

        // Send WA immediately if selected
        if (!empty($request->send_wa_now)) {
            WhatsAppService::broadcastTraining($training);
        }

        return redirect('/training/' . $training->id)->with([
            'success' => 'Jadwal pelatihan berhasil dibuat dengan ' . count($participantsData) . ' peserta terdaftar!',
        ]);
    }

    /**
     * Show training detail, participants attendance, and quiz scores
     */
    public function show($id)
    {
        $training = Training::with([
            'trainer',
            'participants.user.divisi',
            'participants.quizResult',
            'questions',
            'quizResults.user',
        ])->findOrFail($id);

        $totalParticipants = $training->participants->count();
        $attendedCount = $training->participants->where('attendance_status', 'hadir')->count();
        $absentCount = $training->participants->where('attendance_status', 'tidak_hadir')->count();
        $quizSubmittedCount = $training->quizResults->count();
        $avgScore = $quizSubmittedCount > 0 ? round($training->quizResults->avg('score'), 1) : 0;

        return view('training.show', [
            'title' => 'Detail Pelatihan: ' . $training->title,
            'active' => 'training',
            'training' => $training,
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
    public function edit($id)
    {
        $training = Training::with('participants')->findOrFail($id);
        $users = User::orderBy('full_name')->get();
        $divisis = Divisi::with('users')->orderBy('name')->get();
        $selectedParticipants = $training->participants->pluck('user_id')->toArray();

        return view('training.edit', [
            'title' => 'Edit Jadwal Pelatihan',
            'active' => 'training',
            'training' => $training,
            'users' => $users,
            'divisis' => $divisis,
            'selectedParticipants' => $selectedParticipants,
        ]);
    }

    /**
     * Update training details and participants
     */
    public function update(Request $request, $id)
    {
        $training = Training::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'trainer_id' => 'required|exists:users,id',
            'description' => 'nullable|string',
            'training_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'zoom_link' => 'required|string',
            'status' => 'required|in:scheduled,ongoing,completed,cancelled',
            'participants' => 'required|array|min:1',
            'participants.*' => 'exists:users,id',
            'quiz_mode' => 'nullable|in:formal,game',
            'is_attendance_active' => 'nullable|boolean',
            'require_attendance_proof' => 'nullable|boolean',
        ]);

        $updateData = [
            'title' => $validated['title'],
            'trainer_id' => $validated['trainer_id'],
            'description' => $validated['description'] ?? null,
            'training_date' => $validated['training_date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'zoom_link' => $validated['zoom_link'],
            'status' => $validated['status'],
            'quiz_mode' => $request->input('quiz_mode', $training->quiz_mode ?? 'formal'),
        ];

        if ($request->has('is_attendance_active')) {
            $updateData['is_attendance_active'] = $request->boolean('is_attendance_active');
        }
        if ($request->has('require_attendance_proof')) {
            $updateData['require_attendance_proof'] = $request->boolean('require_attendance_proof');
        }

        $training->update($updateData);

        // Sync participants
        $newParticipantIds = array_unique($validated['participants']);
        $existingParticipants = $training->participants()->pluck('user_id')->toArray();

        // Add newly added participants
        foreach ($newParticipantIds as $userId) {
            if (!in_array($userId, $existingParticipants)) {
                TrainingParticipant::create([
                    'training_id' => $training->id,
                    'user_id' => $userId,
                    'token' => Str::random(40) . '_' . time(),
                    'attendance_status' => 'pending',
                ]);
            }
        }

        // Delete removed participants
        $toRemove = array_diff($existingParticipants, $newParticipantIds);
        if (!empty($toRemove)) {
            $training->participants()->whereIn('user_id', $toRemove)->delete();
        }

        return redirect('/training/' . $training->id)->with([
            'success' => 'Pelatihan berhasil diperbarui!',
        ]);
    }

    /**
     * Update training status
     */
    public function updateStatus(Request $request, $id)
    {
        $training = Training::findOrFail($id);
        $status = $request->validate(['status' => 'required|in:scheduled,ongoing,completed,cancelled'])['status'];

        $training->update(['status' => $status]);

        return back()->with('success', "Status pelatihan berhasil diubah menjadi: {$status}");
    }

    /**
     * Toggle quiz active status
     */
    public function toggleQuiz($id)
    {
        $training = Training::findOrFail($id);
        $newStatus = !$training->is_quiz_active;
        $training->update(['is_quiz_active' => $newStatus]);

        $message = $newStatus 
            ? 'Kuis pelatihan telah DIBUKA untuk seluruh peserta!' 
            : 'Kuis pelatihan telah DITUTUP sementara.';

        return back()->with('success', $message);
    }

    /**
     * Toggle attendance active status (Open/Close Absen)
     */
    public function toggleAttendance($id)
    {
        $training = Training::findOrFail($id);
        $newStatus = !$training->is_attendance_active;
        $training->update(['is_attendance_active' => $newStatus]);

        $message = $newStatus 
            ? 'Presensi kehadiran pelatihan telah DIBUKA untuk seluruh peserta!' 
            : 'Presensi kehadiran pelatihan telah DITUTUP sementara.';

        return back()->with('success', $message);
    }

    /**
     * Toggle whether screenshot proof is required for attendance
     */
    public function toggleAttendanceProof($id)
    {
        $training = Training::findOrFail($id);
        $newStatus = !$training->require_attendance_proof;
        $training->update(['require_attendance_proof' => $newStatus]);

        $message = $newStatus 
            ? 'Screenshot bukti kehadiran (Zoom/Pelatihan) kini DIWAJIBKAN untuk peserta!' 
            : 'Syarat upload screenshot bukti kehadiran kini DINONAKTIFKAN (opsional).';

        return back()->with('success', $message);
    }

    /**
     * Quick toggle quiz mode (formal <-> game)
     */
    public function toggleQuizMode($id)
    {
        $training = Training::findOrFail($id);
        $newMode = ($training->quiz_mode === 'game') ? 'formal' : 'game';
        $training->update(['quiz_mode' => $newMode]);

        $label = ($newMode === 'game') ? 'Mode Game Interaktif (Quizizz Style)' : 'Mode Ujian Formal';
        return back()->with('success', "Mode tampilan kuis berhasil diubah ke: {$label}");
    }

    /**
     * Broadcast WhatsApp notification to all participants
     */
    public function broadcastWa($id)
    {
        $training = Training::findOrFail($id);
        $result = WhatsAppService::broadcastTraining($training);

        return back()->with([
            'success' => "Broadcast WhatsApp selesai: {$result['success']} berhasil, {$result['failed']} gagal dari total {$result['total']} peserta.",
        ]);
    }

    /**
     * Send WA notification to a single participant
     */
    public function sendSingleWa($id, $participantId)
    {
        $participant = TrainingParticipant::where('training_id', $id)->findOrFail($participantId);
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
    public function resetParticipantQuiz($id, $participantId)
    {
        $participant = TrainingParticipant::where('training_id', $id)->findOrFail($participantId);
        TrainingQuizResult::where('training_participant_id', $participant->id)->delete();
        TrainingQuizResult::where('training_id', $id)->where('user_id', $participant->user_id)->delete();

        return back()->with('success', "Status kuis untuk {$participant->user->full_name} berhasil di-reset. Peserta dapat mengerjakan kuis kembali.");
    }

    /**
     * Reset participant attendance status back to 'pending' (Belum Absen)
     */
    public function resetParticipantAttendance($id, $participantId)
    {
        $participant = TrainingParticipant::where('training_id', $id)->findOrFail($participantId);

        // Delete proof image file from disk if exists
        if ($participant->attendance_proof && Storage::disk('public')->exists($participant->attendance_proof)) {
            Storage::disk('public')->delete($participant->attendance_proof);
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
    public function updateParticipantAttendance(Request $request, $id, $participantId)
    {
        $participant = TrainingParticipant::where('training_id', $id)->findOrFail($participantId);

        $validated = $request->validate([
            'attendance_status' => 'required|in:pending,hadir,tidak_hadir',
            'attendance_notes' => 'nullable|string|max:255',
        ]);

        $data = [
            'attendance_status' => $validated['attendance_status'],
            'attendance_notes' => $validated['attendance_notes'] ?? null,
        ];

        if ($validated['attendance_status'] === 'pending') {
            if ($participant->attendance_proof && Storage::disk('public')->exists($participant->attendance_proof)) {
                Storage::disk('public')->delete($participant->attendance_proof);
            }
            $data['attended_at'] = null;
            $data['attendance_proof'] = null;
        } elseif ($validated['attendance_status'] === 'hadir' && !$participant->attended_at) {
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
    public function gradeEssay(Request $request, $id, $quizResultId)
    {
        $training = Training::findOrFail($id);
        $result = TrainingQuizResult::where('training_id', $training->id)->findOrFail($quizResultId);

        $validated = $request->validate([
            'essay_score' => 'required|numeric|min:0|max:100',
            'essay_feedback' => 'nullable|string|max:500',
        ]);

        $essayScore = (float)$validated['essay_score'];
        $mcScore = $result->mc_score !== null ? (float)$result->mc_score : 100;

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
     * Return live attendance & quiz stats as JSON for admin polling
     */
    public function liveStats($id)
    {
        $training = Training::with(['participants.quizResult'])->findOrFail($id);

        $participants = $training->participants;
        $total        = $participants->count();
        $attended     = $participants->where('attendance_status', 'hadir')->count();
        $absent       = $participants->where('attendance_status', 'tidak_hadir')->count();
        $pending      = $total - $attended - $absent;
        $quizDone     = $participants->filter(fn($p) => $p->quizResult !== null)->count();
        $avgScore     = $quizDone > 0
            ? round($participants->filter(fn($p) => $p->quizResult)->avg(fn($p) => $p->quizResult->score), 1)
            : 0;

        return response()->json([
            'total'          => $total,
            'attended'       => $attended,
            'absent'         => $absent,
            'pending'        => $pending,
            'quizSubmitted'  => $quizDone,
            'avgScore'       => $avgScore,
            'is_attendance_active' => (bool) $training->is_attendance_active,
            'is_quiz_active'       => (bool) $training->is_quiz_active,
        ]);
    }

    /**
     * Delete a training
     */
    public function destroy($id)
    {
        $training = Training::findOrFail($id);
        $training->delete();

        return redirect('/training')->with('success', 'Pelatihan berhasil dihapus!');
    }

    /**
     * Export attendance and quiz results to Excel (.xlsx)
     */
    public function export($id)
    {
        $training = Training::with([
            'trainer',
            'participants.user.divisi',
            'participants.quizResult',
        ])->findOrFail($id);

        $filename = 'rekap_pelatihan_' . Str::slug($training->title) . '_' . date('Ymd_His') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\TrainingRekapExport($training),
            $filename
        );
    }
}
