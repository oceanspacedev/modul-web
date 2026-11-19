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
use App\Services\OdReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Satu Dashboard untuk semua, isinya mengikuti hak akses user: bagian OD Report untuk yang punya akses OD, dan
     * tiap ringkasan lain (pengguna, pelatihan, video, dokumen, master data) hanya untuk yang boleh membuka menunya
     * serta punya izin view-dashboard. Route-nya memakai gate open-dashboard (view-dashboard atau akses OD).
     */
    public function index(Request $request, OdReportService $odReports): View
    {
        $user = $request->user();
        $show = $this->visibleSections($user);

        return view('dashboard.index', [
            'title' => 'Dashboard',
            'active' => 'dashboard',
            'show' => $show,
            'od' => $user->can('access-od-report') ? $odReports->dashboard($user) : null,
            'data' => in_array(true, $show, true) ? $this->overview($show) : null,
        ]);
    }

    /**
     * Bagian Dashboard yang boleh dilihat user, sama dengan menu yang boleh ia buka.
     *
     * @return array{users: bool, trainings: bool, videos: bool, documents: bool, masterData: bool}
     */
    private function visibleSections(User $user): array
    {
        $stats = $user->can('view-dashboard');

        return [
            'users' => $stats && $user->can('manage-users'),
            'trainings' => $stats && $user->can('manage-trainings'),
            'videos' => $stats && $user->canAny(['view-videos', 'manage-videos']),
            'documents' => $stats && $user->canAny(['view-documents', 'manage-documents']),
            'masterData' => $stats && $user->can('manage-master-data'),
        ];
    }

    /**
     * Angka dan data grafik, hanya untuk bagian yang tampil.
     *
     * @param  array{users: bool, trainings: bool, videos: bool, documents: bool, masterData: bool}  $show
     * @return array<string, mixed>
     */
    private function overview(array $show): array
    {
        $data = [];

        if ($show['users']) {
            $data['totalUser'] = User::count();
        }

        if ($show['documents'] || $show['masterData']) {
            $data['totalDocument'] = Document::count();
        }

        if ($show['videos'] || $show['masterData']) {
            $data['totalVideo'] = Video::count();
        }

        if ($show['trainings'] || $show['masterData']) {
            $data['totalTraining'] = Training::count();
        }

        if ($show['trainings']) {
            $data += $this->trainingOverview();
        }

        if ($show['videos']) {
            $data['recentVideos'] = Video::orderBy('created_at', 'desc')->take(4)->get();

            // Grafik popularitas video materi.
            $videosForChart = Video::orderBy('views_count', 'desc')->take(5)->get();
            $data['chartVideoLabels'] = $videosForChart->map(fn ($v) => Str::limit($v->title, 22))->values();
            $data['chartVideoViews'] = $videosForChart->map(fn ($v) => $v->views_count)->values();
        }

        if ($show['masterData']) {
            $data['totalDivisi'] = Divisi::count();
            $data['totalPosition'] = Position::count();
            $data['totalDokumenType'] = DokumenType::count();

            // Komposisi konten.
            $data['chartContentLabels'] = ['Video Materi', 'Dokumen Modul', 'Pelatihan'];
            $data['chartContentValues'] = [$data['totalVideo'], $data['totalDocument'], $data['totalTraining']];
        }

        return $data;
    }

    /**
     * Pelatihan, peserta dan nilai kuis (untuk yang mengelola pelatihan).
     *
     * @return array<string, mixed>
     */
    private function trainingOverview(): array
    {
        // Hanya peserta pelatihan yang masih ada; pelatihan yang dihapus tidak ikut dihitung.
        $participants = TrainingParticipant::whereHas('training');

        $trainingsForChart = Training::withCount('participants')->orderBy('id', 'desc')->take(6)->get();

        // Sebaran nilai dihitung database supaya halaman tidak memuat semua nilai.
        // "Main phase" = posttest, atau pretest pada pelatihan yang hanya punya pretest.
        $scoreStats = TrainingQuizResult::mainPhase()->toBase()
            ->selectRaw('COUNT(*) as total, AVG(score) as average, MAX(score) as highest, MIN(score) as lowest')
            ->selectRaw('SUM(CASE WHEN score >= 85 THEN 1 ELSE 0 END) as sangat_baik')
            ->selectRaw('SUM(CASE WHEN score >= 70 AND score < 85 THEN 1 ELSE 0 END) as baik')
            ->selectRaw('SUM(CASE WHEN score >= 55 AND score < 70 THEN 1 ELSE 0 END) as cukup')
            ->selectRaw('SUM(CASE WHEN score < 55 THEN 1 ELSE 0 END) as kurang')
            ->first();
        $hasScores = $scoreStats->total > 0;

        return [
            'ongoingTraining' => Training::where('status', TrainingStatus::Ongoing)->count(),
            'totalParticipant' => (clone $participants)->count(),
            'uniqueParticipant' => (clone $participants)->distinct()->count('user_id'),
            'totalQuizResult' => (int) $scoreStats->total,
            'recentTrainings' => Training::with('trainer')->withCount(['participants', 'questions'])
                ->orderBy('training_date', 'desc')->take(5)->get(),
            'recentQuizResults' => TrainingQuizResult::mainPhase()->with(['participant.user', 'training'])
                ->orderBy('created_at', 'desc')->take(5)->get(),
            'chartTrainingLabels' => $trainingsForChart->map(fn ($t) => Str::limit($t->title, 20))->values(),
            'chartTrainingParticipants' => $trainingsForChart->map(fn ($t) => $t->participants_count)->values(),
            'scoreBuckets' => [
                'sangat_baik' => (int) $scoreStats->sangat_baik, // >= 85
                'baik' => (int) $scoreStats->baik,               // 70 - 84
                'cukup' => (int) $scoreStats->cukup,             // 55 - 69
                'kurang' => (int) $scoreStats->kurang,           // < 55
            ],
            'quizAvgScore' => $hasScores ? round((float) $scoreStats->average, 1) : 0,
            'quizMaxScore' => $hasScores ? round((float) $scoreStats->highest, 1) : 0,
            'quizMinScore' => $hasScores ? round((float) $scoreStats->lowest, 1) : 0,
        ];
    }
}
