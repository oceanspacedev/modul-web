<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizHistory;
use App\Models\QuizQuestion;
use App\Models\QuizUserAnswer;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    public function isquizable(Quiz $quiz)
    {
        try {
            $isExist = QuizHistory::where('quiz_id', $quiz->id)->where('user_id', auth()->id())->first();
            if ($isExist->value ?? false) {
                return ResponseFormatter::error(null, 'Anda sudah mengikuti quiz ini');
            }

            if (!$isExist) {
                $create = QuizHistory::create([
                    'user_id' => auth()->id(),
                    'quiz_id' => $quiz->id,
                    'total_question' => $quiz->document->question->count(),
                ]);
            }


            return ResponseFormatter::success($create ?? 'ok', 'berhasil');
        } catch (Exception $e) {
            return ResponseFormatter::error(null, $e->getMessage());
        }
    }

    public function getquestion(Quiz $quiz)
    {
        try {
            $questions = QuizQuestion::with(['options' => function ($q) {
                $q->orderBy('content');
            }])
                ->where('document_id', $quiz->document->id)
                ->orderBy('question')
                ->get();
            foreach ($questions as $question) {
                $question['quiz_id'] = $quiz->id;
            }
            return ResponseFormatter::success($questions, 'berhasil');
        } catch (Exception $e) {
            return ResponseFormatter::error(null, $e->getMessage());
        }
    }

    public function answer(Request $request, QuizQuestion $quizQuestion)
    {
        try {
            $quizAnswer = QuizUserAnswer::create([
                'user_id' => auth()->id(),
                'quiz_id' => $request->quiz_id,
                'quiz_question_id' => $quizQuestion->id,
                'quiz_option_id' => $request->quiz_option_id,
                'value' => $request->value
            ]);
            return ResponseFormatter::success($quizAnswer, 'berhasil');
        } catch (Exception $e) {
            return ResponseFormatter::error(null, $e->getMessage());
        }
    }

    public function calculate(Quiz $quiz)
    {
        try {
            $point = 0;
            $totalQuestion = $quiz->document->question->count();
            $answers = QuizUserAnswer::with(['quiz', 'question', 'option'])
                ->where('user_id', auth()->id())
                ->where('quiz_id', $quiz->id)
                ->get();
            foreach ($answers as $answer) {
                $point += $answer->value;
            }
            $total = round(($point / $totalQuestion) * 100);

            $history = QuizHistory::with(['quiz.document'])->where('quiz_id', $quiz->id)->where('user_id', auth()->id())->first();
            $history->update([
                'correct_answer' => $point,
                'value' => $total,
            ]);
            return ResponseFormatter::success(
                $history,
                'berhasil'
            );
        } catch (Exception $e) {
            return ResponseFormatter::error(null, $e->getMessage());
        }
    }

    public function history()
    {
        try {
            $history = QuizHistory::with(['quiz.document' => function ($q) {
                $q->withTrashed();
            }])->where('user_id', auth()->id())->get();
            return ResponseFormatter::success($history, 'berhasil');
        } catch (Exception $e) {
            return ResponseFormatter::error(null, $e->getMessage());
        }
    }

    public function getscore(Request $request)
    {
        $startMonth = Carbon::parse($request->month)->startOfMonth()->format('Y-m-d');
        $endMonth = Carbon::parse($request->month)->endOfMonth()->format('Y-m-d');

        try {
            $highestValue = QuizHistory::with(['user', 'user.divisi', 'user.subdivisi', 'user.joblevel'])
            ->whereHas('user', function ($q) {
                $q->where('divisi_id', auth()->user()->divisi_id);
            })
            ->whereBetween('created_at', [$startMonth, $endMonth])
                ->selectRaw('user_id, sum(value) as highestValue')
                ->groupBy('user_id')
                ->orderBy('highestValue', 'DESC')
                ->get();
            return ResponseFormatter::success($highestValue, 'berhasil');
        } catch (Exception $e) {
            return ResponseFormatter::error(null, $e->getMessage());
        }
    }
}
