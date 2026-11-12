<?php

namespace App\Exports;

use App\Exports\Concerns\WritesTextAsText;
use App\Models\Training;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Satu baris per peserta per pelatihan yang tanggalnya masuk rentang. Kolomnya mengikuti pengaturan aktif pelatihan
 * di rentang itu: hanya langkah yang dipakai (presensi, pretest, form evaluasi, posttest) yang mendapat kolom, dalam
 * Urutan Langkah Peserta (lihat TrainingStepColumns). Pelatihan yang tidak memakai sebuah langkah mengisi kolomnya
 * dengan "-".
 */
class TrainingScoresSheet implements FromArray, ShouldAutoSize, WithCustomValueBinder, WithEvents, WithHeadings, WithStyles, WithTitle
{
    use WritesTextAsText;

    /** @var Collection<int, Training>|null  dimuat sekali, dipakai judul kolom dan barisnya */
    private ?Collection $trainings = null;

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
            'Tanggal', 'Pelatihan', 'Pemateri', 'ID Karyawan', 'Nama Peserta', 'Divisi',
            ...array_merge([], ...array_map(fn (string $step) => TrainingStepColumns::headings($step, essay: $this->hasEssay($step)), $this->steps())),
        ];
    }

    public function array(): array
    {
        $steps = $this->steps();
        $essay = array_combine($steps, array_map(fn (string $step) => $this->hasEssay($step), $steps));

        $rows = [];
        foreach ($this->trainings() as $training) {
            foreach ($training->participants->sortBy(fn ($participant) => $participant->user?->nama_lengkap) as $participant) {
                $user = $participant->user;

                $rows[] = [
                    $training->training_date->format('d-m-Y'),
                    $training->title,
                    $training->trainer->nama_lengkap ?? '-',
                    $user->employee_id ?? '-',
                    $user->nama_lengkap ?? '-',
                    $user?->divisi->name ?? '-',
                    ...array_merge([], ...array_map(fn (string $step) => TrainingStepColumns::cells($step, $training, $participant, essay: $essay[$step]), $steps)),
                ];
            }
        }

        return $rows;
    }

    /**
     * @return Collection<int, Training>
     */
    private function trainings(): Collection
    {
        return $this->trainings ??= Training::query()
            ->whereDate('training_date', '>=', $this->from->toDateString())
            ->whereDate('training_date', '<=', $this->to->toDateString())
            ->with([
                'trainer',
                // Hanya untuk tahu fase mana yang memakai soal essay.
                'questions:id,training_id,type,used_in',
                'evaluationQuestions',
                'participants' => fn ($query) => $query->when($this->divisiId, fn ($q) => $q->whereHas(
                    'user',
                    fn ($user) => $user->withTrashed()->where('divisi_id', $this->divisiId),
                )),
                'participants.user' => fn ($query) => $query->withTrashed()->with('divisi'),
                'participants.quizResult',
                'participants.pretestResult',
                'participants.evaluation',
            ])
            ->orderBy('training_date')
            ->orderBy('title')
            ->get()
            // Pelatihan tanpa peserta (mis. tersaring divisi) tidak punya baris, jadi tidak ikut menentukan kolom.
            ->filter(fn (Training $training) => $training->participants->isNotEmpty())
            ->values();
    }

    /**
     * Langkah yang mendapat kolom: yang dipakai setidaknya satu pelatihan di rentang ini. Urutannya mengikuti Urutan
     * Langkah Peserta bila semua pelatihan memakai urutan yang sama, selain itu urutan bawaan.
     *
     * @return list<string>
     */
    private function steps(): array
    {
        $trainings = $this->trainings();
        $orders = $trainings->map(fn (Training $training) => implode(',', $training->portalStepOrder()))->unique()->values();
        $order = $orders->count() === 1 ? explode(',', $orders[0]) : array_keys(Training::PORTAL_STEPS);

        return array_values(array_filter(
            $order,
            fn (string $step) => $trainings->contains(fn (Training $training) => TrainingStepColumns::uses($training, $step)),
        ));
    }

    /**
     * Kolom PG/essay terpisah hanya bila ada pelatihan di rentang ini yang fase itu memakai soal essay.
     */
    private function hasEssay(string $step): bool
    {
        return $this->trainings()->contains(fn (Training $training) => TrainingStepColumns::hasEssay($training, $step));
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
