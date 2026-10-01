<?php

namespace App\Imports;

use App\Models\QuizOption;
use App\Models\QuizQuestion;
use Exception;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

class QuestionImport implements ToCollection, WithStartRow
{
    protected int $documentId;

    function __construct(int $documentId)
    {
        $this->documentId = $documentId;
    }


    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $containTrue = array();
            $question = QuizQuestion::create([
                'document_id' => $this->documentId,
                'question' => $row[0],
                'seconds' => $row[6],
            ]);
            for ($i = 2; $i < 6; $i++) {
                $quizOption = QuizOption::create([
                    'quiz_question_id' => $question->id,
                    'content' => $row[$i],
                    'is_true' => $row[$i] == $row[1],
                ]);
                array_push($containTrue, $quizOption->is_true);
            }
            $countTrue = count(array_filter($containTrue));
            if ($countTrue != 1) {
                throw new Exception('Error pada pertanyaan ' . $question->question . ' Tidak ada salah satu jawaban yang benar');
            }
        }
    }

    public function startRow(): int
    {
        return 2;
    }
}
