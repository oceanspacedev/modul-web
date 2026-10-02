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
        ]);

        $training->update([
            'title' => $validated['title'],
            'trainer_id' => $validated['trainer_id'],
            'description' => $validated['description'] ?? null,
            'training_date' => $validated['training_date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'zoom_link' => $validated['zoom_link'],
            'status' => $validated['status'],
            'quiz_mode' => $request->input('quiz_mode', $training->quiz_mode ?? 'formal'),
        ]);

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
     * Delete a training
     */
    public function destroy($id)
    {
        $training = Training::findOrFail($id);
        $training->delete();

        return redirect('/training')->with('success', 'Pelatihan berhasil dihapus!');
    }

    /**
     * Export attendance and quiz results to CSV
     */
    public function export($id)
    {
        $training = Training::with(['participants.user.divisi', 'participants.quizResult'])->findOrFail($id);

        $filename = 'rekap_pelatihan_' . Str::slug($training->title) . '_' . date('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($training) {
            $file = fopen('php://output', 'w');
            
            // UTF-8 BOM for Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header info
            fputcsv($file, ['REKAP HASIL PELATIHAN']);
            fputcsv($file, ['Topik Pelatihan', $training->title]);
            fputcsv($file, ['Pemateri', $training->trainer->full_name ?? '-']);
            fputcsv($file, ['Tanggal', Carbon::parse($training->training_date)->format('d-m-Y')]);
            fputcsv($file, ['Waktu', $training->start_time . ' - ' . $training->end_time]);
            fputcsv($file, []);

            // Column titles
            fputcsv($file, [
                'No',
                'ID Karyawan',
                'Nama Peserta',
                'Divisi',
                'No WhatsApp',
                'Status Kehadiran',
                'Waktu Absen',
                'Status Kuis',
                'Nilai PG (0-100)',
                'Status Essay',
                'Nilai Essay (0-100)',
                'Nilai Akhir (0-100)',
                'Catatan Review Essay',
                'Pelanggaran Keluar Tab',
                'Status Submit',
                'Waktu Submit Kuis',
            ]);

            $no = 1;
            foreach ($training->participants as $participant) {
                $user = $participant->user;
                $quiz = $participant->quizResult;

                $essayStatusLabel = 'Tidak Ada Essay';
                if ($quiz) {
                    if ($quiz->essay_status === 'graded') {
                        $essayStatusLabel = 'Sudah Dinilai';
                    } elseif ($quiz->essay_status === 'pending') {
                        $essayStatusLabel = 'Menunggu Dinilai';
                    }
                }

                $submitTypeLabel = '-';
                if ($quiz) {
                    $submitTypeLabel = $quiz->is_force_submitted ? 'Auto-Submit (Melanggar)' : 'Normal';
                }

                fputcsv($file, [
                    $no++,
                    $user->id_karyawan ?? '-',
                    $user->full_name ?? '-',
                    $user->divisi->name ?? '-',
                    $user->no_wa ?? '-',
                    strtoupper($participant->attendance_status),
                    $participant->attended_at ? Carbon::parse($participant->attended_at)->format('d-m-Y H:i') : '-',
                    $quiz ? 'Sudah Mengerjakan' : 'Belum Mengerjakan',
                    $quiz ? ($quiz->mc_score ?? $quiz->score) : 0,
                    $essayStatusLabel,
                    $quiz && $quiz->essay_score !== null ? $quiz->essay_score : '-',
                    $quiz ? $quiz->score : 0,
                    $quiz ? ($quiz->essay_feedback ?? '-') : '-',
                    $quiz ? (($quiz->tab_switch_count ?? 0) . ' kali') : '-',
                    $submitTypeLabel,
                    $quiz && $quiz->submitted_at ? Carbon::parse($quiz->submitted_at)->format('d-m-Y H:i') : '-',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
