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

        return view('training.portal.quiz', [
            'title' => 'Kuis Evaluasi: ' . $training->title,
            'participant' => $participant,
            'training' => $training,
            'questions' => $questions,
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

        $correctCount = 0;
        $answersDetails = [];

        foreach ($questions as $q) {
            $userAns = $submittedAnswers[$q->id] ?? null;
            $isCorrect = ($userAns === $q->correct_answer);

            if ($isCorrect) {
                $correctCount++;
            }

            $answersDetails[$q->id] = [
                'user_answer' => $userAns,
                'correct_answer' => $q->correct_answer,
                'is_correct' => $isCorrect,
            ];
        }

        $score = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100, 2) : 0;

        TrainingQuizResult::create([
            'training_id' => $training->id,
            'user_id' => $participant->user_id,
            'training_participant_id' => $participant->id,
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctCount,
            'score' => $score,
            'answers' => $answersDetails,
            'submitted_at' => now(),
        ]);

        return redirect("/training/portal/{$token}/result")->with(
            'success', 
            'Jawaban kuis Anda berhasil dikirim! Nilai Anda telah dihitung.'
        );
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
