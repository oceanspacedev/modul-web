<?php

namespace App\Http\Controllers;

use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\TrainingQuizResult;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TrainingPortalController extends Controller
{
    /**
     * Public / Token-based portal for training participant
     */
    public function showPortal($token)
    {
        $participant = TrainingParticipant::with([
            'training.trainer',
            'training.questions',
            'user.divisi',
            'quizResult',
        ])->where('token', $token)->firstOrFail();

        $training = $participant->training;

        return view('training.portal.index', [
            'title' => 'Portal Pelatihan: ' . $training->title,
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
        $participant = TrainingParticipant::where('token', $token)->firstOrFail();

        $validated = $request->validate([
            'status' => 'required|in:hadir,tidak_hadir',
            'notes' => 'nullable|string|max:255',
        ]);

        $participant->update([
            'attendance_status' => $validated['status'],
            'attended_at' => now(),
            'attendance_notes' => $validated['notes'] ?? null,
        ]);

        $msg = $validated['status'] === 'hadir' 
            ? 'Terima kasih! Kehadiran Anda berhasil dicatat sebagai HADIR.' 
            : 'Konfirmasi ketidakhadiran Anda telah tercatat.';

        return back()->with('success', $msg);
    }

    /**
     * Show quiz page for participant
     */
    public function showQuiz($token)
    {
        $participant = TrainingParticipant::with([
            'training.questions',
            'quizResult',
        ])->where('token', $token)->firstOrFail();

        $training = $participant->training;

        // If already submitted quiz and not retaking, redirect to result
        if ($participant->quizResult && request('retake') != 1) {
            return redirect("/training/portal/{$token}/result");
        }

        // If retaking, clear previous attempt
        if (request('retake') == 1) {
            TrainingQuizResult::where('training_participant_id', $participant->id)->delete();
            TrainingQuizResult::where('training_id', $participant->training_id)
                ->where('user_id', $participant->user_id)
                ->delete();
        }

        // Check if quiz is activated by trainer
        if (!$training->is_quiz_active) {
            return redirect("/training/portal/{$token}")->with(
                'warning', 
                'Kuis evaluasi saat ini belum dibuka oleh Pemateri. Harap menunggu instruksi dari pemateri.'
            );
        }

        $questions = $training->questions;

        if ($questions->isEmpty()) {
            return redirect("/training/portal/{$token}")->with(
                'warning', 
                'Belum ada soal kuis yang disediakan untuk pelatihan ini.'
            );
        }

        $viewName = ($training->quiz_mode === 'game') ? 'training.portal.quiz_game' : 'training.portal.quiz';

        $leaderboard = [];
        if ($training->quiz_mode === 'game') {
            $leaderboard = TrainingQuizResult::with('user')
                ->where('training_id', $training->id)
                ->orderByDesc('score')
                ->orderBy('submitted_at')
                ->take(10)
                ->get();
        }

        return view($viewName, [
            'title' => 'Kuis Evaluasi: ' . $training->title,
            'participant' => $participant,
            'training' => $training,
            'questions' => $questions,
            'leaderboard' => $leaderboard,
        ]);
    }

    /**
     * Retake quiz action
     */
    public function retakeQuiz($token)
    {
        $participant = TrainingParticipant::where('token', $token)->firstOrFail();
        
        TrainingQuizResult::where('training_participant_id', $participant->id)->delete();
        TrainingQuizResult::where('training_id', $participant->training_id)
            ->where('user_id', $participant->user_id)
            ->delete();

        return redirect("/training/portal/{$token}/quiz")->with(
            'success', 
            'Kuis telah di-reset. Silakan kerjakan soal terbaru!'
        );
    }

    /**
     * Submit quiz answers
     */
    public function submitQuiz(Request $request, $token)
    {
        $participant = TrainingParticipant::with(['training.questions', 'quizResult'])
            ->where('token', $token)
            ->firstOrFail();

        $training = $participant->training;

        // Clear previous results to record fresh score
        TrainingQuizResult::where('training_participant_id', $participant->id)->delete();
        TrainingQuizResult::where('training_id', $training->id)
            ->where('user_id', $participant->user_id)
            ->delete();

        $questions = $training->questions;
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
                $isCorrect = ($userAns && strtolower((string)$userAns) === strtolower((string)$q->correct_answer));

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

        // Anti-cheat parameters
        $tabSwitchCount = (int)$request->input('tab_switch_count', 0);
        $isForceSubmitted = (bool)$request->input('is_force_submitted', false);
        $violationLogsInput = $request->input('violation_logs');
        $violationLogs = is_string($violationLogsInput) ? json_decode($violationLogsInput, true) : (is_array($violationLogsInput) ? $violationLogsInput : []);

        // Calculate score based on multiple choice questions, or 100 if quiz only contains essay
        $mcScore = $mcTotal > 0 ? round(($correctCount / $mcTotal) * 100, 2) : 100;
        $essayStatus = ($essayTotal > 0) ? 'pending' : 'none';

        TrainingQuizResult::create([
            'training_id' => $training->id,
            'user_id' => $participant->user_id,
            'training_participant_id' => $participant->id,
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctCount,
            'score' => $mcScore,
            'mc_score' => $mcScore,
            'essay_score' => null,
            'essay_status' => $essayStatus,
            'tab_switch_count' => $tabSwitchCount,
            'is_force_submitted' => $isForceSubmitted,
            'violation_logs' => $violationLogs,
            'answers' => $answersDetails,
            'submitted_at' => now(),
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            $updatedLeaderboard = TrainingQuizResult::with('user')
                ->where('training_id', $training->id)
                ->orderByDesc('score')
                ->orderBy('submitted_at')
                ->take(10)
                ->get()
                ->map(function($r, $idx) {
                    return [
                        'rank' => $idx + 1,
                        'user_id' => $r->user_id,
                        'name' => $r->user ? $r->user->full_name : 'Peserta',
                        'score' => (float)$r->score,
                    ];
                });

            return response()->json([
                'success' => true,
                'score' => $mcScore,
                'leaderboard' => $updatedLeaderboard,
                'redirect_url' => "/training/portal/{$token}/result",
            ]);
        }

        $flashMsg = $isForceSubmitted 
            ? 'Kuis telah otomatis dikumpulkan karena Anda terdeteksi berpindah tab melebihi batas toleransi!'
            : 'Jawaban kuis Anda berhasil dikirim dan tersimpan di sistem!';

        $flashType = $isForceSubmitted ? 'warning' : 'success';

        return redirect("/training/portal/{$token}/result")->with($flashType, $flashMsg);
    }

    /**
     * Show quiz score & result summary
     */
    public function showResult($token)
    {
        $participant = TrainingParticipant::with([
            'training.questions',
            'training.trainer',
            'quizResult',
            'user',
        ])->where('token', $token)->firstOrFail();

        $quizResult = $participant->quizResult;

        if (!$quizResult) {
            return redirect("/training/portal/{$token}")->with('warning', 'Anda belum menyelesaikan kuis.');
        }

        return view('training.portal.result', [
            'title' => 'Hasil Kuis: ' . $participant->training->title,
            'participant' => $participant,
            'training' => $participant->training,
            'quizResult' => $quizResult,
            'questions' => $participant->training->questions,
        ]);
    }

    /**
     * List of trainings for logged-in user
     */
    public function myTrainings()
    {
        $userId = auth()->id();

        $participations = TrainingParticipant::with(['training.trainer', 'quizResult'])
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
