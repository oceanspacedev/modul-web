<?php

namespace App\Exports;

use App\Exports\Concerns\WritesTextAsText;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Template import peserta pelatihan. Judul kolomnya mengikuti file karyawan dari HR (Employee ID, Full Name, Job
 * Position) ditambah No HP yang wajib diisi.
 */
class TrainingParticipantTemplate implements FromCollection, WithCustomValueBinder, WithEvents, WithHeadings
{
    use WritesTextAsText;

    public function collection(): Collection
    {
        return collect([
            ['2019.01.14.01', 'RUDI HARTONO', '081234567890', 'HEAD STORE (CJ1)', 'RETAIL'],
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Employee ID', 'Full Name', 'No HP', 'Job Position', 'Divisi'];
    }
}
