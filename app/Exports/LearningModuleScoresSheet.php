<?php

namespace App\Exports;

use App\Exports\Concerns\WritesTextAsText;
use App\Models\LearningModule;
use App\Models\LearningModuleItem;
use App\Support\LearningModuleProgress;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Satu baris per peserta per modul/kuis video/kuis dokumen, untuk peserta yang terakhir mengerjakan pretest/posttest
 * di dalam rentang tanggal. Kolom nilai sama dengan LearningModuleRekapSheet.
 */
class LearningModuleScoresSheet implements FromArray, ShouldAutoSize, WithCustomValueBinder, WithEvents, WithHeadings, WithStyles, WithTitle
{
    use WritesTextAsText;

    private const TYPE_LABELS = [
        'standalone' => 'Modul Belajar',
        LearningModuleItem::TYPE_VIDEO => 'Kuis Video',
        LearningModuleItem::TYPE_DOCUMENT => 'Kuis Dokumen',
    ];

    /**
     * @param  list<string>  $types  jenis modul dari ScoreRecapExport::MODULE_TYPES
     */
    public function __construct(
        protected CarbonImmutable $from,
        protected CarbonImmutable $to,
        protected array $types,
        protected ?int $divisiId = null,
    ) {}

    public function title(): string
    {
        // Bukan "Pretest & Posttest": pretest/posttest pelatihan ada di sheet Pelatihan.
        return 'Modul & Kuis Materi';
    }

    public function headings(): array
    {
        return [
            'Jenis', 'Judul', 'Nilai Minimal Lulus', 'ID Karyawan', 'Nama Peserta', 'Divisi', 'Jabatan',
            'Nilai Pretest', 'Jumlah Posttest', 'Posttest Terbaik', 'Posttest Terakhir', 'Kenaikan Nilai', 'Status', 'Terakhir Mengerjakan',
        ];
    }

    public function array(): array
    {
        $modules = LearningModule::query()
            ->where(function (Builder $query) {
                $sourceTypes = array_values(array_diff($this->types, ['standalone']));
                $query->whereIn('source_type', $sourceTypes);
                if (in_array('standalone', $this->types, true)) {
                    $query->orWhereNull('source_type');
                }
            })
            ->whereHas('attempts', fn ($query) => $query->whereBetween('submitted_at', [$this->from, $this->to]))
            ->with('items')
            ->orderBy('title')
            ->get();

        $rows = [];
        foreach ($modules as $module) {
            $participants = LearningModuleProgress::forParticipants($module)->filter(function (LearningModuleProgress $progress) {
                $lastActivity = $progress->lastActivityAt();

                return $lastActivity !== null
                    && $lastActivity->between($this->from, $this->to)
                    && ($this->divisiId === null || (int) $progress->user->divisi_id === $this->divisiId);
            });

            foreach ($participants as $progress) {
                $user = $progress->user;
                $improvement = $progress->improvement();

                $rows[] = [
                    self::TYPE_LABELS[$module->routeContext()] ?? 'Modul Belajar',
                    $module->title,
                    $module->passing_score,
                    $user->employee_id ?? '-',
                    $user->nama_lengkap ?? '-',
                    $user->divisi->name ?? '-',
                    $user->jabatan->name ?? '-',
                    $progress->pretest?->score ?? '-',
                    $progress->posttests->count(),
                    $progress->bestPosttest()?->score ?? '-',
                    $progress->latestPosttest()?->score ?? '-',
                    $improvement === null ? '-' : ($improvement > 0 ? '+' : '').$improvement,
                    $progress->statusLabel(),
                    $progress->lastActivityAt()?->format('d-m-Y H:i') ?? '-',
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
