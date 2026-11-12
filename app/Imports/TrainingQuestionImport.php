<?php

namespace App\Imports;

use App\Models\Training;
use App\Models\TrainingQuestion;
use Exception;

/**
 * Kolom: tipe_soal, pertanyaan, kunci_jawaban, pilihan_a..pilihan_f, penjelasan, dipakai_di
 * (lihat TrainingQuestionTemplate). Aturan bacanya ada di QuestionSheetImport.
 */
class TrainingQuestionImport extends QuestionSheetImport
{
    private const CHOICE_TYPES = ['pg', 'pilihan ganda', 'pilihan_ganda', 'multiple choice', 'multiple_choice', 'mc'];

    private const ESSAY_TYPES = ['essay', 'esai', 'uraian', 'text'];

    /** Tulisan kolom dipakai_di yang dikenal => nilai used_in. */
    private const USED_IN = [
        'keduanya' => TrainingQuestion::USED_IN_BOTH,
        'both' => TrainingQuestion::USED_IN_BOTH,
        'pretest & posttest' => TrainingQuestion::USED_IN_BOTH,
        'pretest dan posttest' => TrainingQuestion::USED_IN_BOTH,
        'pretest' => Training::PHASE_PRETEST,
        'pretest saja' => Training::PHASE_PRETEST,
        'posttest' => Training::PHASE_POSTTEST,
        'posttest saja' => Training::PHASE_POSTTEST,
    ];

    public function __construct(protected int $trainingId) {}

    protected function requiredColumns(): array
    {
        return ['tipe_soal', 'pertanyaan', 'kunci_jawaban', 'pilihan_a', 'pilihan_b'];
    }

    protected function optionalColumns(): array
    {
        return ['pilihan_c', 'pilihan_d', 'pilihan_e', 'pilihan_f', 'penjelasan', 'dipakai_di'];
    }

    protected function importRow(array $row, int $rowNumber): void
    {
        if ($row['pertanyaan'] === '') {
            throw new Exception("Baris ke-{$rowNumber}: kolom pertanyaan kosong padahal kolom lain terisi. Isi pertanyaannya atau kosongkan seluruh baris.");
        }

        $letters = TrainingQuestion::OPTION_LETTERS;
        $hasOptions = collect($letters)->contains(fn (string $letter) => $row['pilihan_'.$letter] !== '');
        $isEssay = $this->isEssay($row['tipe_soal'], $hasOptions, $rowNumber);

        if ($isEssay && $hasOptions) {
            throw new Exception("Baris ke-{$rowNumber}: soal ESSAY tidak boleh punya pilihan jawaban. Kosongkan kolom pilihan, atau ubah tipe_soal menjadi PG.");
        }

        $options = $isEssay ? [] : $this->options($row, $letters, $rowNumber);

        TrainingQuestion::create([
            'training_id' => $this->trainingId,
            'type' => $isEssay ? 'essay' : 'multiple_choice',
            'question' => $row['pertanyaan'],
            ...collect($letters)->mapWithKeys(fn (string $letter) => ['option_'.$letter => $options[$letter] ?? null])->all(),
            'correct_answer' => $isEssay
                ? ($row['kunci_jawaban'] !== '' ? $row['kunci_jawaban'] : null)
                : $this->keyLetter($row['kunci_jawaban'], $options, $letters, $rowNumber),
            'explanation' => $row['penjelasan'] !== '' ? $row['penjelasan'] : null,
            // Kosong = bawaan (pilihan ganda di kedua kuis, essay hanya di posttest).
            ...($row['dipakai_di'] !== '' ? ['used_in' => $this->usedIn($row['dipakai_di'], $rowNumber)] : []),
        ]);
    }

    /**
     * tipe_soal harus PG atau ESSAY. Bila kosong, baris yang punya pilihan dibaca sebagai PG; tanpa pilihan tipenya
     * tidak bisa dipastikan, jadi ditolak.
     */
    private function isEssay(string $rawType, bool $hasOptions, int $rowNumber): bool
    {
        $type = strtolower($rawType);

        return match (true) {
            in_array($type, self::ESSAY_TYPES, true) => true,
            in_array($type, self::CHOICE_TYPES, true) => false,
            $type === '' && $hasOptions => false,
            $type === '' => throw new Exception("Baris ke-{$rowNumber}: tipe_soal kosong dan tidak ada pilihan jawaban. Isi tipe_soal dengan PG atau ESSAY."),
            default => throw new Exception("Baris ke-{$rowNumber}: tipe_soal \"{$rawType}\" tidak dikenal. Isi dengan PG atau ESSAY."),
        };
    }

    private function usedIn(string $value, int $rowNumber): string
    {
        return self::USED_IN[strtolower(preg_replace('/\s+/', ' ', $value))]
            ?? throw new Exception("Baris ke-{$rowNumber}: dipakai_di \"{$value}\" tidak dikenal. Isi dengan KEDUANYA, PRETEST, atau POSTTEST (atau kosongkan).");
    }
}
