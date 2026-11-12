<?php

namespace App\Http\Controllers;

use App\Models\Training;
use App\Models\TrainingEvaluation;
use App\Models\TrainingParticipant;
use App\Models\TrainingQuizResult;
use App\Support\TrainingEvaluationForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TrainingPortalController extends Controller
{
    /**
     * Helper: cari participant by token, abort 404 ramah jika tidak ditemukan
     */
    private function findParticipant(string $token, array $with = [])
    {
        $query = TrainingParticipant::where('token', $token);

        if (! empty($with)) {
            $query->with($with);
        }

        $participant = $query->first();

        if (! $participant) {
            abort(404);
        }

        return $participant;
    }

    /**
     * Alamat portal sebuah fase: posttest memakai quiz/result (alamat lama), pretest memakai pretest/...
     *
     * @return array{quiz: string, result: string, portal: string}
     */
    private function phaseUrls(string $token, string $phase): array
    {
        $base = "/training/portal/{$token}";

        return $phase === Training::PHASE_PRETEST
            ? ['quiz' => $base.'/pretest', 'result' => $base.'/pretest/result', 'portal' => $base]
            : ['quiz' => $base.'/quiz', 'result' => $base.'/result', 'portal' => $base];
    }

    /**
     * Public / Token-based portal for training participant
     */
    public function showPortal($token)
    {
        $participant = $this->findParticipant($token, [
            'training.trainer',
            'training.questions',
            'training.documents.versions',
            'training.documents.dokumentype',
            'training.documents.quizModule',
            'training.evaluationQuestions',
            'user.divisi',
            'quizResult',
            'pretestResult',
            'evaluation',
        ]);

        $training = $participant->training;

        return view('training.portal.index', [
            'title' => 'Portal Pelatihan: '.$training->title,
            'participant' => $participant,
            'training' => $training,
            'user' => $participant->user,
        ]);
    }

    /**
     * Submit attendance by participant
     */
    public function submitAttendance(Request $request, $token)
    {
        $participant = $this->findParticipant($token, ['training']);
        $training = $participant->training;

        if (! $training->has_attendance) {
            return back()->with('warning', 'Pelatihan ini tidak memakai presensi.');
        }

        // Presensi punya jam buka dan jam tutup sendiri; kiriman masih diterima sebentar setelah jam tutupnya lewat.
        if (! $training->acceptsAttendanceSubmission()) {
            $opensAt = $training->phaseOpensAt(Training::STEP_ATTENDANCE);

            return back()->with('warning', match (true) {
                $opensAt !== null => 'Presensi dibuka otomatis '.Training::timeLabel($opensAt).' WIB.',
                $training->phaseTimedOut(Training::STEP_ATTENDANCE) => 'Waktu pengisian presensi sudah habis.',
                default => 'Presensi kehadiran saat ini belum dibuka oleh Pemateri / Admin. Harap menunggu instruksi dari pemateri.',
            });
        }

        $rules = [
            'status' => 'required|in:hadir,tidak_hadir',
            'notes' => 'nullable|string|max:255',
        ];

        // Validate screenshot proof if required when marking "hadir"
        if ($training->require_attendance_proof && $request->input('status') === 'hadir') {
            $rules['attendance_proof'] = 'required|image|mimes:jpeg,png,jpg,webp|max:5120';
        } else {
            $rules['attendance_proof'] = 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120';
        }

        $messages = [
            'attendance_proof.required' => $training->isOnline()
                ? 'Wajib mengunggah screenshot bukti Anda mengikuti pelatihan / Zoom untuk konfirmasi kehadiran.'
                : 'Wajib mengunggah foto bukti kehadiran Anda di lokasi pelatihan.',
            'attendance_proof.image' => 'File bukti kehadiran harus berupa gambar (JPG, PNG, atau WEBP).',
            'attendance_proof.max' => 'Ukuran file bukti kehadiran maksimal 5MB.',
        ];

        $validated = $request->validate($rules, $messages);

        $proofPath = $participant->attendance_proof;
        if ($request->hasFile('attendance_proof')) {
            if ($proofPath && Storage::exists($proofPath)) {
                Storage::delete($proofPath);
            }
            $file = $request->file('attendance_proof');
            $fileName = Str::random(30).'.'.$file->getClientOriginalExtension();
            $proofPath = $file->storeAs('attendance_proofs', $fileName);
        }

        $participant->update([
            'attendance_status' => $validated['status'],
            'attended_at' => now(),
            'attendance_notes' => $validated['notes'] ?? null,
            'attendance_proof' => $proofPath,
        ]);

        $msg = $validated['status'] === 'hadir'
            ? 'Terima kasih! Kehadiran Anda berhasil dicatat sebagai HADIR.'
            : 'Konfirmasi ketidakhadiran Anda telah tercatat.';

        return back()->with('success', $msg);
    }

    /**
     * Show quiz page for participant
     */
    public function showQuiz($token, string $phase = Training::PHASE_POSTTEST)
    {
        $participant = $this->findParticipant($token, [
            'training.questions',
            'quizResult',
            'pretestResult',
            'evaluation',
        ]);

        $training = $participant->training;
        $urls = $this->phaseUrls($token, $phase);
        $label = $phase === Training::PHASE_PRETEST ? 'Pretest' : 'Kuis';
        $previous = $participant->resultFor($phase);

        if (! $training->hasPhase($phase)) {
            return redirect($urls['portal'])->with('warning', "{$label} tidak diadakan untuk pelatihan ini.");
        }

        // Already submitted: back to the result once the allowed attempts are used up
        if ($previous && $training->quizAttemptsLeft($previous, $phase) === 0) {
            return redirect($urls['result'])->with(
                'warning',
                "Anda sudah menyelesaikan {$label} ini. {$label} tidak dapat dikerjakan ulang."
            );
        }

        // Check if this phase is opened by trainer
        if (! $training->isPhaseActive($phase)) {
            return redirect($urls['portal'])->with(
                'warning',
                $phase === Training::PHASE_PRETEST
                    ? 'Pretest saat ini belum dibuka oleh Pemateri. Harap menunggu instruksi dari pemateri.'
                    : 'Kuis evaluasi saat ini belum dibuka oleh Pemateri. Harap menunggu instruksi dari pemateri.'
            );
        }

        $questions = $training->questionsFor($phase);

        if ($questions->isEmpty()) {
            return redirect($urls['portal'])->with(
                'warning',
                'Belum ada soal kuis yang disediakan untuk pelatihan ini.'
            );
        }

        // Form evaluasi diisi tepat sebelum posttest. Hanya pintu masuk kuis yang dijaga: jawaban peserta yang sudah
        // mengerjakan saat pemateri menyalakan evaluasi tetap diterima.
        if ($phase === Training::PHASE_POSTTEST && $training->requiresEvaluationBeforePosttest($participant)) {
            return redirect($urls['portal'].'/evaluation')->with(
                'warning',
                'Isi form evaluasi pelatihan terlebih dahulu, setelah itu kuis langsung terbuka.'
            );
        }

        // Pretest dan posttest boleh memakai mode berbeda.
        $isGame = $training->quizMode($phase) === 'game';
        $viewName = $isGame ? 'training.portal.quiz_game' : 'training.portal.quiz';

        $leaderboard = [];
        if ($isGame) {
            $leaderboard = TrainingQuizResult::with('user')
                ->where('training_id', $training->id)
                ->where('phase', $phase)
                ->orderByDesc('score')
                ->orderBy('submitted_at')
                ->take(10)
                ->get();
        }

        return view($viewName, [
            'title' => ($phase === Training::PHASE_PRETEST ? 'Pretest: ' : 'Kuis Evaluasi: ').$training->title,
            'participant' => $participant,
            'training' => $training,
            'questions' => $questions,
            'leaderboard' => $leaderboard,
            'phase' => $phase,
            'phaseUrls' => $urls,
            'showsAnswers' => $training->showsAnswers($phase),
            'secondsLeft' => $training->phaseSecondsLeft($phase),
            'closesAt' => $training->phaseClosesAt($phase),
        ]);
    }

    /**
     * Retake quiz: allowed only while attempts remain and the quiz is open.
     */
    public function retakeQuiz($token, string $phase = Training::PHASE_POSTTEST)
    {
        $participant = $this->findParticipant($token, ['training', 'quizResult', 'pretestResult']);
        $training = $participant->training;
        $urls = $this->phaseUrls($token, $phase);

        if ($training->canRetakeQuiz($participant->resultFor($phase), $phase)) {
            return redirect($urls['quiz']);
        }

        return redirect($urls['result'])->with(
            'warning',
            $training->allowsQuizRetake($phase)
                ? 'Kuis tidak dapat dikerjakan ulang saat ini.'
                : 'Kuis hanya dapat dikerjakan satu kali dan tidak dapat dikerjakan ulang.'
        );
    }

    /**
     * Submit quiz answers
     */
    public function submitQuiz(Request $request, $token, string $phase = Training::PHASE_POSTTEST)
    {
        $participant = $this->findParticipant($token, ['training.questions', 'quizResult', 'pretestResult']);
        $training = $participant->training;
        $urls = $this->phaseUrls($token, $phase);

        if (! $training->hasPhase($phase)) {
            abort(404);
        }

        // Prevent resubmission once the allowed attempts are used up
        $previous = $participant->resultFor($phase);
        if ($previous && $training->quizAttemptsLeft($previous, $phase) === 0) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda sudah menyelesaikan kuis ini dan tidak dapat mengerjakan ulang.',
                    'redirect_url' => $urls['result'],
                ], 403);
            }

            return redirect($urls['result'])->with(
                'warning',
                'Anda sudah menyelesaikan kuis ini dan tidak dapat mengerjakan ulang.'
            );
        }

        $questions = $training->questionsFor($phase);
        $totalQuestions = $questions->count();
        $submittedAnswers = $request->input('answers', []);

        $mcTotal = 0;
        $correctCount = 0;
        $essayTotal = 0;
        $answersDetails = [];

        foreach ($questions as $q) {
            $userAns = $submittedAnswers[$q->id] ?? null;

            if ($q->type === 'essay') {
                $essayTotal++;
                $answersDetails[$q->id] = [
                    'type' => 'essay',
                    'user_answer' => is_string($userAns) ? trim($userAns) : '',
                    'correct_answer' => $q->correct_answer,
                    'is_correct' => null,
                ];
            } else {
                $mcTotal++;
                $isCorrect = ($userAns && strtolower((string) $userAns) === strtolower((string) $q->correct_answer));
                if ($isCorrect) {
                    $correctCount++;
                }

                $answersDetails[$q->id] = [
                    'type' => 'multiple_choice',
                    'user_answer' => $userAns,
                    'correct_answer' => $q->correct_answer,
                    'is_correct' => $isCorrect,
                ];
            }
        }

        $tabSwitchCount = (int) $request->input('tab_switch_count', 0);
        $isForceSubmitted = (bool) $request->input('is_force_submitted', false);
        // Halaman kuis mengumpulkan sendiri saat batas waktu habis; kiriman itu tetap diterima seperti biasa.
        $isTimeUp = $request->boolean('is_time_up');
        $violationLogsInput = $request->input('violation_logs');
        $violationLogs = is_string($violationLogsInput)
            ? json_decode($violationLogsInput, true)
            : (is_array($violationLogsInput) ? $violationLogsInput : []);

        $mcScore = $mcTotal > 0 ? round(($correctCount / $mcTotal) * 100, 2) : 100;
        $essayStatus = ($essayTotal > 0) ? 'pending' : 'none';

        $attemptData = [
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctCount,
            'score' => $mcScore,
            'mc_score' => $mcScore,
            'essay_score' => null,
            'essay_status' => $essayStatus,
            'essay_feedback' => null,
            'reviewed_at' => null,
            'tab_switch_count' => $tabSwitchCount,
            'is_force_submitted' => $isForceSubmitted,
            'violation_logs' => $violationLogs,
            'answers' => $answersDetails,
            'submitted_at' => now(),
        ];

        // One result per participant. A retake only replaces it when it scores higher, so the highest score counts.
        $keptPreviousScore = null;
        if ($previous) {
            $attemptCount = ($previous->attempt_count ?? 1) + 1;
            if ($mcScore > (float) $previous->score) {
                $previous->update([...$attemptData, 'attempt_count' => $attemptCount]);
            } else {
                $previous->update(['attempt_count' => $attemptCount]);
                $keptPreviousScore = (float) $previous->score;
            }
        } else {
            TrainingQuizResult::create([
                ...$attemptData,
                'training_id' => $training->id,
                'user_id' => $participant->user_id,
                'training_participant_id' => $participant->id,
                'phase' => $phase,
                'attempt_count' => 1,
            ]);
        }

        if ($request->ajax() || $request->wantsJson()) {
            if ($keptPreviousScore !== null) {
                session()->flash('warning', "Nilai percobaan ini {$mcScore}. Nilai tertinggi Anda ({$keptPreviousScore}) tetap dipakai.");
            }

            $updatedLeaderboard = TrainingQuizResult::with('user')
                ->where('training_id', $training->id)
                ->where('phase', $phase)
                ->orderByDesc('score')
                ->orderBy('submitted_at')
                ->take(10)
                ->get()
                ->map(function ($r, $idx) {
                    return [
                        'rank' => $idx + 1,
                        'user_id' => $r->user_id,
                        'name' => $r->user ? $r->user->nama_lengkap : 'Peserta',
                        'score' => (float) $r->score,
                    ];
                });

            return response()->json([
                'success' => true,
                'score' => $mcScore,
                'leaderboard' => $updatedLeaderboard,
                'redirect_url' => $urls['result'],
            ]);
        }

        $flashMsg = match (true) {
            $isForceSubmitted => 'Kuis telah otomatis dikumpulkan karena Anda terdeteksi berpindah tab melebihi batas toleransi!',
            $isTimeUp => 'Waktu pengerjaan habis. Jawaban yang sudah Anda isi otomatis dikumpulkan.',
            default => 'Jawaban kuis Anda berhasil dikirim dan tersimpan di sistem!',
        };
        $flashType = $isForceSubmitted || $isTimeUp ? 'warning' : 'success';

        if ($keptPreviousScore !== null) {
            $flashMsg = "Nilai percobaan ini {$mcScore}. Nilai tertinggi Anda ({$keptPreviousScore}) tetap dipakai.";
            $flashType = 'warning';
        }

        return redirect($urls['result'])->with($flashType, $flashMsg);
    }

    /**
     * Show quiz score & result summary
     */
    public function showResult($token, string $phase = Training::PHASE_POSTTEST)
    {
        $participant = $this->findParticipant($token, [
            'training.questions',
            'training.trainer',
            'quizResult',
            'pretestResult',
            'user',
        ]);

        $training = $participant->training;
        $quizResult = $participant->resultFor($phase);

        if (! $quizResult) {
            return redirect("/training/portal/{$token}")->with('warning', 'Anda belum menyelesaikan kuis.');
        }

        return view('training.portal.result', [
            'title' => ($phase === Training::PHASE_PRETEST ? 'Hasil Pretest: ' : 'Hasil Kuis: ').$training->title,
            'participant' => $participant,
            'training' => $training,
            'quizResult' => $quizResult,
            'questions' => $training->questionsFor($phase),
            'phase' => $phase,
            'phaseUrls' => $this->phaseUrls($token, $phase),
            'showsAnswers' => $training->showsAnswers($phase),
        ]);
    }

    /**
     * Evaluation form with this training's own questions. Name, position, division, date, and topic come from the
     * participant and the training, so they are shown, not asked.
     */
    public function showEvaluation($token)
    {
        $participant = $this->findParticipant($token, [
            'training.trainer',
            'training.evaluationQuestions',
            'user.divisi',
            'user.position',
            'user.area',
            'user.roles',
            'quizResult',
            'evaluation',
        ]);
        $training = $participant->training;

        if ($blocked = $this->evaluationBlocked($training, $participant, "/training/portal/{$token}")) {
            return $blocked;
        }

        return view('training.portal.evaluation', [
            'title' => 'Evaluasi Pelatihan: '.$training->title,
            'participant' => $participant,
            'training' => $training,
            'user' => $participant->user,
            'questionCount' => $training->evaluationQuestions->count(),
            'sections' => TrainingEvaluationForm::sections($training->evaluationQuestions),
            'continuesToQuiz' => $this->quizFollowsEvaluation($training, $participant),
        ]);
    }

    public function submitEvaluation(Request $request, $token)
    {
        $participant = $this->findParticipant($token, ['training.evaluationQuestions', 'quizResult', 'evaluation']);
        $training = $participant->training;
        $portal = "/training/portal/{$token}";

        if (! $training->hasEvaluationForm()) {
            abort(404);
        }

        if ($blocked = $this->evaluationBlocked($training, $participant, $portal, submitting: true)) {
            return $blocked;
        }

        $questions = $training->evaluationQuestions;
        $validated = $request->validate(TrainingEvaluationForm::rules($questions), TrainingEvaluationForm::messages());

        // Satu jawaban per peserta: kiriman ganda (klik dua kali) tidak membuat baris kedua.
        TrainingEvaluation::firstOrCreate(
            ['training_participant_id' => $participant->id],
            [
                'training_id' => $training->id,
                'user_id' => $participant->user_id,
                'answers' => TrainingEvaluationForm::answersFromInput($questions, $validated['answers'] ?? []),
                'submitted_at' => now(),
            ],
        );

        if ($this->quizFollowsEvaluation($training, $participant)) {
            return redirect($portal.'/quiz')->with('success', 'Terima kasih, evaluasi Anda tersimpan. Silakan kerjakan kuis.');
        }

        return redirect($portal)->with('success', 'Terima kasih, evaluasi Anda tersimpan.');
    }

    /**
     * Redirect back to the portal when the evaluation cannot be filled in right now; null when it can.
     */
    private function evaluationBlocked(Training $training, TrainingParticipant $participant, string $portal, bool $submitting = false): ?RedirectResponse
    {
        // A form that is being submitted is still accepted shortly after its closing time passed.
        $open = $submitting ? $training->acceptsEvaluationSubmission() : $training->isEvaluationOpen();

        $reason = match (true) {
            ! $training->hasEvaluationForm() => 'Form evaluasi tidak diadakan untuk pelatihan ini.',
            $participant->evaluation !== null => 'Anda sudah mengisi form evaluasi pelatihan ini.',
            $open => null,
            $training->evaluationTimedOut() => 'Waktu pengisian form evaluasi sudah habis.',
            $training->evaluationOpensAt() !== null => 'Form evaluasi dibuka otomatis '.Training::timeLabel($training->evaluationOpensAt()).' WIB.',
            default => 'Form evaluasi belum dibuka. Harap menunggu instruksi dari pemateri.',
        };

        return $reason ? redirect($portal)->with('warning', $reason) : null;
    }

    /**
     * After an evaluation that comes before the posttest, the participant goes straight into the posttest when it is
     * open, has questions, and they still have an attempt left.
     */
    private function quizFollowsEvaluation(Training $training, TrainingParticipant $participant): bool
    {
        return $training->evaluationFollowsPosttest()
            && $training->isPhaseActive(Training::PHASE_POSTTEST)
            && $training->phaseQuestions(Training::PHASE_POSTTEST)->exists()
            && $training->quizAttemptsLeft($participant->quizResult) !== 0;
    }

    /**
     * Return current training status as JSON — used by participant portal for live polling
     */
    public function getStatus($token)
    {
        $participant = $this->findParticipant($token, ['training', 'quizResult']);
        $training = $participant->training;
        // Dipanggil tiap beberapa detik oleh setiap peserta: cek adanya form evaluasi cukup sekali.
        $hasEvaluationForm = $training->hasEvaluationForm();

        return response()->json([
            'is_attendance_active' => $training->isAttendanceOpen(),
            'is_quiz_active' => $training->isPhaseActive(Training::PHASE_POSTTEST),
            'is_pretest_active' => $training->isPhaseActive(Training::PHASE_PRETEST),
            // Sisa detik sampai tutup otomatis (null = tanpa batas waktu atau tertutup); dihitung server supaya jam
            // perangkat peserta yang meleset tidak berpengaruh.
            'quiz_seconds_left' => $training->phaseSecondsLeft(Training::PHASE_POSTTEST),
            'pretest_seconds_left' => $training->phaseSecondsLeft(Training::PHASE_PRETEST),
            'quiz_closes_at' => $training->phaseClosesAt(Training::PHASE_POSTTEST)?->format('H:i'),
            'pretest_closes_at' => $training->phaseClosesAt(Training::PHASE_PRETEST)?->format('H:i'),
            // Jadwal buka (mis. "pukul 09:40"); null bila tidak dijadwalkan atau sudah terbuka.
            'quiz_opens_at' => $this->opensLabel($training, Training::PHASE_POSTTEST),
            'pretest_opens_at' => $this->opensLabel($training, Training::PHASE_PRETEST),
            'quiz_timed_out' => $training->phaseTimedOut(Training::PHASE_POSTTEST),
            'pretest_timed_out' => $training->phaseTimedOut(Training::PHASE_PRETEST),
            // Presensi berjadwal/berbatas waktu, sama seperti pretest dan posttest.
            'attendance_seconds_left' => $training->phaseSecondsLeft(Training::STEP_ATTENDANCE),
            'attendance_closes_at' => $training->phaseClosesAt(Training::STEP_ATTENDANCE)?->format('H:i'),
            'attendance_opens_at' => $this->opensLabel($training, Training::STEP_ATTENDANCE),
            'attendance_timed_out' => $training->phaseTimedOut(Training::STEP_ATTENDANCE),
            'has_evaluation' => $hasEvaluationForm,
            'is_evaluation_open' => $hasEvaluationForm && $training->evaluationWindowOpen(),
            'require_attendance_proof' => (bool) $training->require_attendance_proof,
            'attendance_status' => $participant->attendance_status,
            'has_quiz_result' => $participant->quizResult ? true : false,
        ]);
    }

    private function opensLabel(Training $training, string $phase): ?string
    {
        $opensAt = $training->phaseOpensAt($phase);

        return $opensAt ? Training::timeLabel($opensAt) : null;
    }

    /**
     * List of trainings for logged-in user
     */
    public function myTrainings()
    {
        $userId = auth()->id();

        $participations = TrainingParticipant::with(['training.trainer', 'training.evaluationQuestions', 'quizResult', 'pretestResult', 'evaluation'])
            ->where('user_id', $userId)
            ->whereHas('training')
            ->latest()
            ->paginate(10);

        return view('training.portal.my_trainings', [
            'title' => 'Pelatihan Saya',
            'active' => 'my-trainings',
            'participations' => $participations,
        ]);
    }
}
