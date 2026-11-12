<?php

namespace App\Imports;

use App\Models\LearningModule;
use App\Models\LearningModuleQuestion;
use Exception;

/**
 * Kolom: pertanyaan, kunci_jawaban, pilihan_a..pilihan_e, penjelasan (lihat LearningModuleQuestionTemplate). Aturan
 * bacanya ada di QuestionSheetImport.
 */
class LearningModuleQuestionImport extends QuestionSheetImport
{
    public function __construct(protected LearningModule $module, protected int $sortOrder) {}

    protected function requiredColumns(): array
    {
        return ['pertanyaan', 'kunci_jawaban', 'pilihan_a', 'pilihan_b'];
    }

    protected function optionalColumns(): array
    {
        return ['pilihan_c', 'pilihan_d', 'pilihan_e', 'penjelasan'];
    }

    protected function importRow(array $row, int $rowNumber): void
    {
        if ($row['pertanyaan'] === '') {
            throw new Exception("Baris ke-{$rowNumber}: kolom pertanyaan kosong padahal kolom lain terisi. Isi pertanyaannya atau kosongkan seluruh baris.");
        }

        $letters = LearningModuleQuestion::OPTION_LETTERS;
        $options = $this->options($row, $letters, $rowNumber);

        $this->module->questions()->create([
            'question' => $row['pertanyaan'],
            ...collect($letters)->mapWithKeys(fn (string $letter) => ['option_'.$letter => $options[$letter] ?? null])->all(),
            'correct_answer' => $this->keyLetter($row['kunci_jawaban'], $options, $letters, $rowNumber),
            'explanation' => $row['penjelasan'] !== '' ? $row['penjelasan'] : null,
            'sort_order' => $this->sortOrder++,
        ]);
    }
}
