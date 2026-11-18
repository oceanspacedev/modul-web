<?php

namespace App\Exports;

use App\Exports\Concerns\WritesTextAsText;
use App\Models\DocumentRequest;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Permintaan Dokumen, satu baris per proses (seperti ekspor CSV aplikasi lama).
 */
class DocumentRequestExport implements FromArray, ShouldAutoSize, WithCustomValueBinder, WithEvents, WithHeadings
{
    use WritesTextAsText;

    public function __construct(private User $viewer) {}

    public function headings(): array
    {
        return [
            'Kode', 'Tanggal', 'Nama', 'Divisi', 'Departemen', 'Jabatan', 'Atasan', 'WhatsApp', 'Email',
            'Proses ke', 'Judul proses', 'Tujuan', 'Prioritas', 'Sistem/alat', 'Tahap', 'Status OD', 'PIC',
            'Observasi pilihan 1', 'Observasi pilihan 2', 'Catatan observasi', 'Jadwal observasi', 'Status tiket', 'Progres tiket (%)', 'Catatan internal',
        ];
    }

    public function array(): array
    {
        $rows = [];

        DocumentRequest::query()->visibleTo($this->viewer)->with(['processes.status', 'processes.pic'])->latest()->get()
            ->each(function (DocumentRequest $request) use (&$rows) {
                foreach ($request->processes as $index => $process) {
                    $rows[] = [
                        $request->code, $request->tanggal->format('Y-m-d'), $request->nama, $request->divisi, $request->departemen,
                        $request->jabatan, (string) $request->atasan, $request->whatsapp, $request->email,
                        $index + 1, $process->title, (string) $process->tujuan, (string) $process->prioritas, (string) $process->sistem,
                        $process->publicStage(), $process->status->name, (string) $process->pic?->nama_lengkap,
                        $request->obs_tanggal_1->format('Y-m-d'), (string) $request->obs_tanggal_2?->format('Y-m-d'), (string) $request->obs_catatan,
                        (string) $request->jadwal_observasi?->format('Y-m-d'), $request->stage(), $request->progress(), (string) $request->catatan_admin,
                    ];
                }
            });

        return $rows;
    }
}
