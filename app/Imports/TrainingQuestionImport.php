<?php

namespace App\Imports;

use App\Models\TrainingQuestion;
use Exception;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

class TrainingQuestionImport implements ToCollection, WithStartRow
{
    protected int $trainingId;

    protected int $importedCount = 0;

    public function __construct(int $trainingId)
    {
        $this->trainingId = $trainingId;
    }

    public function startRow(): int
    {
        return 2;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // offset for 1-based index and header row

            $rawType = trim((string) ($row[0] ?? ''));
            $questionText = trim((string) ($row[1] ?? ''));

            // Skip empty rows
            if ($questionText === '') {
                continue;
            }

            $rawKey = trim((string) ($row[2] ?? ''));
            $optA = trim((string) ($row[3] ?? '')) ?: null;
            $optB = trim((string) ($row[4] ?? '')) ?: null;
            $optC = trim((string) ($row[5] ?? '')) ?: null;
            $optD = trim((string) ($row[6] ?? '')) ?: null;
            $optE = trim((string) ($row[7] ?? '')) ?: null;
            $optF = trim((string) ($row[8] ?? '')) ?: null;
            $explanation = trim((string) ($row[9] ?? '')) ?: null;

            $isEssay = in_array(strtolower($rawType), ['essay', 'esai', 'uraian', 'text']);

            if ($isEssay) {
                TrainingQuestion::create([
                    'training_id' => $this->trainingId,
                    'type' => 'essay',
                    'question' => $questionText,
                    'option_a' => null,
                    'option_b' => null,
                    'option_c' => null,
                    'option_d' => null,
                    'option_e' => null,
                    'option_f' => null,
                    'correct_answer' => $rawKey ?: null,
                    'explanation' => $explanation,
                ]);
                $this->importedCount++;
            } else {
                // Multiple Choice Validation: At least Option A and Option B must be filled
                if (! $optA || ! $optB) {
                    throw new Exception("Baris ke-{$rowNumber}: Soal Pilihan Ganda '{$questionText}' harus memiliki minimal Pilihan A dan Pilihan B.");
                }

                // Determine correct key ('a', 'b', 'c', 'd', 'e', 'f')
                $keyLetter = strtolower($rawKey);
                $validOptions = [
                    'a' => $optA,
                    'b' => $optB,
                    'c' => $optC,
                    'd' => $optD,
                    'e' => $optE,
                    'f' => $optF,
                ];

                // If user wrote the full answer text instead of letter, match it
                if (! array_key_exists($keyLetter, $validOptions) || empty($validOptions[$keyLetter])) {
                    $matchedKey = null;
                    foreach ($validOptions as $letter => $content) {
                        if ($content !== null && strcasecmp(trim($content), $rawKey) === 0) {
                            $matchedKey = $letter;
                            break;
                        }
                    }

                    if ($matchedKey) {
                        $keyLetter = $matchedKey;
                    } else {
                        throw new Exception("Baris ke-{$rowNumber}: Kunci jawaban '{$rawKey}' untuk soal '{$questionText}' tidak valid atau tidak cocok dengan pilihan yang tersedia.");
                    }
                }

                TrainingQuestion::create([
                    'training_id' => $this->trainingId,
                    'type' => 'multiple_choice',
                    'question' => $questionText,
                    'option_a' => $optA,
                    'option_b' => $optB,
                    'option_c' => $optC,
                    'option_d' => $optD,
                    'option_e' => $optE,
                    'option_f' => $optF,
                    'correct_answer' => $keyLetter,
                    'explanation' => $explanation,
                ]);
                $this->importedCount++;
            }
        }
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }
}
