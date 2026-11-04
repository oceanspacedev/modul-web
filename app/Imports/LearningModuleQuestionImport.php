<?php

namespace App\Imports;

use App\Models\LearningModule;
use Exception;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

/**
 * Kolom: pertanyaan, kunci_jawaban, pilihan_a..pilihan_e, penjelasan (lihat LearningModuleQuestionTemplate).
 */
class LearningModuleQuestionImport implements ToCollection, WithStartRow
{
    protected int $importedCount = 0;

    public function __construct(protected LearningModule $module, protected int $sortOrder) {}

    public function startRow(): int
    {
        return 2;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // offset for 1-based index and header row

            $questionText = trim((string) ($row[0] ?? ''));

            // Skip empty rows
            if ($questionText === '') {
                continue;
            }

            $rawKey = trim((string) ($row[1] ?? ''));
            $options = [];
            foreach (['a', 'b', 'c', 'd', 'e'] as $offset => $letter) {
                $options[$letter] = trim((string) ($row[2 + $offset] ?? '')) ?: null;
            }
            $explanation = trim((string) ($row[7] ?? '')) ?: null;

            if (! $options['a'] || ! $options['b']) {
                throw new Exception("Baris ke-{$rowNumber}: Soal '{$questionText}' harus memiliki minimal Pilihan A dan Pilihan B.");
            }

            // Kunci boleh berupa huruf atau teks jawaban lengkap.
            $keyLetter = strtolower($rawKey);
            if (empty($options[$keyLetter] ?? null)) {
                $keyLetter = collect($options)->search(fn ($content) => $content !== null && strcasecmp($content, $rawKey) === 0);

                if ($keyLetter === false) {
                    throw new Exception("Baris ke-{$rowNumber}: Kunci jawaban '{$rawKey}' untuk soal '{$questionText}' tidak valid atau tidak cocok dengan pilihan yang tersedia.");
                }
            }

            $this->module->questions()->create([
                'question' => $questionText,
                'option_a' => $options['a'],
                'option_b' => $options['b'],
                'option_c' => $options['c'],
                'option_d' => $options['d'],
                'option_e' => $options['e'],
                'correct_answer' => $keyLetter,
                'explanation' => $explanation,
                'sort_order' => $this->sortOrder++,
            ]);
            $this->importedCount++;
        }
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }
}
