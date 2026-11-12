<?php

namespace App\Exports;

use App\Models\Training;
use Carbon\CarbonImmutable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Satu baris per peserta per pelatihan yang tanggalnya masuk rentang. Kolom nilai sama dengan TrainingRekapExport.
 */
class TrainingScoresSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        protected CarbonImmutable $from,
        protected CarbonImmutable $to,
        protected ?int $divisiId = null,
    ) {}

    public function title(): string
    {
        return 'Pelatihan';
    }

    public function headings(): array
    {
        return [
            'Tanggal', 'Pelatihan', 'Pemateri', 'ID Karyawan', 'Nama Peserta', 'Divisi', 'Kehadiran',
            'Status Kuis', 'Nilai PG (0-100)', 'Status Essay', 'Nilai Essay (0-100)', 'Nilai Akhir (0-100)', 'Waktu Submit Kuis', 'Nilai Pretest (0-100)',
        ];
    }

    public function array(): array
    {
        $trainings = Training::query()
            ->whereDate('training_date', '>=', $this->from->toDateString())
            ->whereDate('training_date', '<=', $this->to->toDateString())
            ->with([
                'trainer',
                'participants' => fn ($query) => $query->when($this->divisiId, fn ($q) => $q->whereHas(
                    'user',
                    fn ($user) => $user->withTrashed()->where('divisi_id', $this->divisiId),
                )),
                'participants.user' => fn ($query) => $query->withTrashed()->with('divisi'),
                'participants.quizResult',
                'participants.pretestResult',
            ])
            ->orderBy('training_date')
            ->orderBy('title')
            ->get();

        $rows = [];
        foreach ($trainings as $training) {
            foreach ($training->participants->sortBy(fn ($participant) => $participant->user?->nama_lengkap) as $participant) {
                $user = $participant->user;
                // Kolom kuis adalah posttest; pelatihan tanpa posttest mengisinya dengan "-".
                $quiz = $training->has_posttest ? $participant->quizResult : null;
                $pretest = $training->has_pretest ? $participant->pretestResult : null;

                $rows[] = [
                    $training->training_date->format('d-m-Y'),
                    $training->title,
                    $training->trainer->nama_lengkap ?? '-',
                    $user->employee_id ?? '-',
                    $user->nama_lengkap ?? '-',
                    $user?->divisi->name ?? '-',
                    match (true) {
                        ! $training->has_attendance => '-',
                        $participant->attendance_status === 'hadir' => 'Hadir',
                        $participant->attendance_status === 'tidak_hadir' => 'Tidak Hadir',
                        default => 'Belum Absen',
                    },
                    match (true) {
                        ! $training->has_posttest => '-',
                        $quiz !== null => 'Sudah Mengerjakan',
                        default => 'Belum Mengerjakan',
                    },
                    $quiz ? (float) ($quiz->mc_score ?? $quiz->score) : '-',
                    match ($quiz?->essay_status) {
                        'graded' => 'Sudah Dinilai',
                        'pending' => 'Menunggu Dinilai',
                        default => $training->has_posttest ? 'Tidak Ada Essay' : '-',
                    },
                    $quiz && $quiz->essay_score !== null ? (float) $quiz->essay_score : '-',
                    $quiz ? (float) $quiz->score : '-',
                    $quiz?->submitted_at?->format('d-m-Y H:i') ?? '-',
                    match (true) {
                        ! $training->has_pretest => '-',
                        $pretest !== null => (float) $pretest->score,
                        default => 'Belum Mengerjakan',
                    },
                ];
            }
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
