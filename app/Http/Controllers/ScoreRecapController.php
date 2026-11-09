<?php

namespace App\Http\Controllers;

use App\Exports\ScoreRecapExport;
use App\Jobs\GenerateExportFile;
use App\Models\Divisi;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Rekap nilai gabungan: nilai pelatihan dan pretest/posttest (modul, kuis video, kuis dokumen) dari banyak kegiatan
 * sekaligus. Bagian yang bisa dipilih mengikuti izin kelola masing-masing; file dibuat di belakang layar.
 */
class ScoreRecapController extends Controller
{
    /** Bagian rekap => [izin yang dibutuhkan, label]. */
    private const SECTIONS = [
        ScoreRecapExport::SECTION_TRAININGS => ['manage-trainings', 'Pelatihan'],
        'standalone' => ['manage-learning-modules', 'Pretest & posttest Modul Belajar'],
        'video' => ['manage-videos', 'Pretest & posttest Kuis Video'],
        'document' => ['manage-documents', 'Pretest & posttest Kuis Dokumen'],
    ];

    /** Izin yang membuka menu Rekap Nilai. */
    public const PERMISSIONS = ['manage-trainings', 'manage-learning-modules', 'manage-videos', 'manage-documents'];

    public function index(Request $request): View
    {
        return view('score-recap.index', [
            'title' => 'Rekap Nilai',
            'active' => 'score-recap',
            'sections' => $this->allowedSections($request->user()),
            'divisis' => Divisi::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function export(Request $request): RedirectResponse
    {
        $allowed = array_keys($this->allowedSections($request->user()));

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => [Rule::in($allowed)],
            'divisi_id' => ['nullable', 'integer', 'exists:divisis,id'],
        ], [
            'sections.required' => 'Pilih minimal satu jenis nilai.',
            'to.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
        ]);

        GenerateExportFile::dispatch($request->user()->id, GenerateExportFile::SCORES, null, [
            'from' => $data['from'],
            'to' => $data['to'],
            'sections' => array_values(array_unique($data['sections'])),
            'divisi_id' => isset($data['divisi_id']) ? (int) $data['divisi_id'] : null,
        ]);

        return back()->with('info', GenerateExportFile::QUEUED_MESSAGE);
    }

    /**
     * @return array<string, string> kode bagian => label
     */
    private function allowedSections(User $user): array
    {
        $sections = [];
        foreach (self::SECTIONS as $key => [$permission, $label]) {
            if ($user->can($permission)) {
                $sections[$key] = $label;
            }
        }

        abort_if($sections === [], 403);

        return $sections;
    }
}
