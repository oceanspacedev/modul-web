<?php

namespace App\Http\Controllers;

use App\Enums\TrainingStatus;
use App\Models\Divisi;
use App\Models\Document;
use App\Models\DokumenType;
use App\Models\Position;
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

        // Entity counts for dashboard
        $totalVideo = Video::count();
        $totalTraining = Training::count();
        $ongoingTraining = Training::where('status', TrainingStatus::Ongoing)->count();
        $totalParticipant = TrainingParticipant::count();
        $totalQuizResult = TrainingQuizResult::posttest()->count();
        $totalDivisi = Divisi::count();
        $totalPosition = Position::count();
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
        $recentQuizResults = TrainingQuizResult::posttest()->with(['participant.user', 'training'])
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

        // Chart 2: Quiz Evaluation Score Distribution, counted by the database so the page never loads every score.
        $scoreStats = TrainingQuizResult::posttest()->toBase()
            ->selectRaw('COUNT(*) as total, AVG(score) as average, MAX(score) as highest, MIN(score) as lowest')
            ->selectRaw('SUM(CASE WHEN score >= 85 THEN 1 ELSE 0 END) as sangat_baik')
            ->selectRaw('SUM(CASE WHEN score >= 70 AND score < 85 THEN 1 ELSE 0 END) as baik')
            ->selectRaw('SUM(CASE WHEN score >= 55 AND score < 70 THEN 1 ELSE 0 END) as cukup')
            ->selectRaw('SUM(CASE WHEN score < 55 THEN 1 ELSE 0 END) as kurang')
            ->first();
        $scoreBuckets = [
            'sangat_baik' => (int) $scoreStats->sangat_baik, // >= 85
            'baik' => (int) $scoreStats->baik,               // 70 - 84
            'cukup' => (int) $scoreStats->cukup,             // 55 - 69
            'kurang' => (int) $scoreStats->kurang,           // < 55
        ];
        $quizAvgScore = $scoreStats->total > 0 ? round((float) $scoreStats->average, 1) : 0;
        $quizMaxScore = $scoreStats->total > 0 ? round((float) $scoreStats->highest, 1) : 0;
        $quizMinScore = $scoreStats->total > 0 ? round((float) $scoreStats->lowest, 1) : 0;

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
        $chartContentLabels = ['Video Materi', 'Dokumen Modul', 'Pelatihan', 'Divisi'];
        $chartContentValues = [$totalVideo, $totalDocument, $totalTraining, $totalDivisi];

        return view('dashboard.index', [
            'title' => 'Dashboard',
            'active' => 'dashboard',
            'data' => [
                'totalUser' => $totalUser,
                'totalDocument' => $totalDocument,
                'totalVideo' => $totalVideo,
                'totalTraining' => $totalTraining,
                'ongoingTraining' => $ongoingTraining,
                'totalParticipant' => $totalParticipant,
                'totalQuizResult' => $totalQuizResult,
                'totalDivisi' => $totalDivisi,
                'totalPosition' => $totalPosition,
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
