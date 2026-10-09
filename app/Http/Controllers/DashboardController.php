<?php

namespace App\Http\Controllers;

use App\Models\Absent;
use App\Models\Divisi;
use App\Models\Document;
use App\Models\DokumenType;
use App\Models\Quiz;
use App\Models\QuizHistory;
use App\Models\SubDivisi;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\TrainingQuizResult;
use App\Models\User;
use App\Models\Video;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $totalUser = User::count();
        $totalDocument = Document::count();
        $totalQuiz = Quiz::count();
        $totalHistory = QuizHistory::count();

        // Entity counts for dashboard
        $totalVideo = Video::count();
        $totalTraining = Training::count();
        $ongoingTraining = Training::where('status', 'ongoing')->count();
        $totalParticipant = TrainingParticipant::count();
        $totalQuizResult = TrainingQuizResult::count();
        $totalAbsent = Absent::count();
        $todayAbsent = Absent::whereDate('created_at', today())->count();
        $totalDivisi = Divisi::count();
        $totalSubDivisi = SubDivisi::count();
        $totalDokumenType = DokumenType::count();

        // Recent trainings
        $recentTrainings = Training::with('trainer')
            ->orderBy('training_date', 'desc')
            ->take(5)
            ->get();

        // Recent video materials
        $recentVideos = Video::orderBy('created_at', 'desc')
            ->take(4)
            ->get();

        // Recent quiz evaluation results
        $recentQuizResults = TrainingQuizResult::with(['participant.user', 'training'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // Chart 1: Training Participant Chart Data
        $trainingsForChart = Training::withCount('participants')
            ->orderBy('id', 'desc')
            ->take(6)
            ->get();
        $chartTrainingLabels = $trainingsForChart->map(function ($t) {
            return Str::limit($t->title, 20);
        })->values();
        $chartTrainingParticipants = $trainingsForChart->map(function ($t) {
            return $t->participants_count;
        })->values();

        // Chart 2: Quiz Evaluation Score Distribution
        $quizScores = TrainingQuizResult::pluck('score')->map(function ($s) {
            return (float) $s;
        });
        $scoreBuckets = [
            'sangat_baik' => 0, // >= 85
            'baik' => 0,        // 70 - 84
            'cukup' => 0,       // 55 - 69
            'kurang' => 0,      // < 55
        ];
        foreach ($quizScores as $sc) {
            if ($sc >= 85) {
                $scoreBuckets['sangat_baik']++;
            } elseif ($sc >= 70) {
                $scoreBuckets['baik']++;
            } elseif ($sc >= 55) {
                $scoreBuckets['cukup']++;
            } else {
                $scoreBuckets['kurang']++;
            }
        }
        $quizAvgScore = $quizScores->count() > 0 ? round($quizScores->avg(), 1) : 0;
        $quizMaxScore = $quizScores->count() > 0 ? round($quizScores->max(), 1) : 0;
        $quizMinScore = $quizScores->count() > 0 ? round($quizScores->min(), 1) : 0;

        // Chart 3: Video Views Popularity Chart Data
        $videosForChart = Video::orderBy('views_count', 'desc')
            ->take(5)
            ->get();
        $chartVideoLabels = $videosForChart->map(function ($v) {
            return Str::limit($v->title, 22);
        })->values();
        $chartVideoViews = $videosForChart->map(function ($v) {
            return $v->views_count;
        })->values();

        // Chart 4: Module & Content Distribution
        $chartContentLabels = ['Video Materi', 'Dokumen Modul', 'Pelatihan', 'Kuis Modul', 'Divisi'];
        $chartContentValues = [$totalVideo, $totalDocument, $totalTraining, $totalQuiz, $totalDivisi];

        return view('dashboard.index', [
            'title' => 'Dashboard',
            'active' => 'dashboard',
            'data' => [
                'totalUser' => $totalUser,
                'totalDocument' => $totalDocument,
                'totalQuiz' => $totalQuiz,
                'totalHistory' => $totalHistory,
                'totalVideo' => $totalVideo,
                'totalTraining' => $totalTraining,
                'ongoingTraining' => $ongoingTraining,
                'totalParticipant' => $totalParticipant,
                'totalQuizResult' => $totalQuizResult,
                'totalAbsent' => $totalAbsent,
                'todayAbsent' => $todayAbsent,
                'totalDivisi' => $totalDivisi,
                'totalSubDivisi' => $totalSubDivisi,
                'totalDokumenType' => $totalDokumenType,
                'recentTrainings' => $recentTrainings,
                'recentVideos' => $recentVideos,
                'recentQuizResults' => $recentQuizResults,
                // Charts data
                'chartTrainingLabels' => $chartTrainingLabels,
                'chartTrainingParticipants' => $chartTrainingParticipants,
                'chartVideoLabels' => $chartVideoLabels,
                'chartVideoViews' => $chartVideoViews,
                'scoreBuckets' => $scoreBuckets,
                'quizAvgScore' => $quizAvgScore,
                'quizMaxScore' => $quizMaxScore,
                'quizMinScore' => $quizMinScore,
                'chartContentLabels' => $chartContentLabels,
                'chartContentValues' => $chartContentValues,
            ],
        ]);
    }
}
