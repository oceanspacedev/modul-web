<?php

namespace App\Exports;

use App\Models\QuizHistory;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class QuizResultExport implements FromCollection, WithHeadings, WithMapping
{
    protected int $quizId;
    protected int $divisi_id;
    protected int $kkm;
    protected int $denda;

    function __construct(int $quizId, int $divisi_id, int $kkm, int $denda)
    {
        $this->quizId = $quizId;
        $this->divisi_id = $divisi_id;
        $this->kkm = $kkm;
        $this->denda = $denda;
    }

    public function collection()
    {
        $result = [];
        $users = User::where('divisi_id', $this->divisi_id)->get();
        foreach ($users as $user) {
            $dataQuizUser = QuizHistory::with('quiz')->where('quiz_id', $this->quizId)->where('user_id', $user->id)->withTrashed()->first();
            array_push($result, [
                'full_name' => $user->full_name ?? 'Nonactive user',
                'correct_answer' => $dataQuizUser ==  null ? '-' : $dataQuizUser->correct_answer,
                'wrong_answer' => $dataQuizUser ==  null ? '-' : $dataQuizUser->total_question - ($dataQuizUser->correct_answer ?? 0),
                'total_question' => $dataQuizUser ==  null ? '-' : $dataQuizUser->total_question,
                'value' => $dataQuizUser ==  null ? '-' : $dataQuizUser->value ?? 0,
                'potongan' => $dataQuizUser == null ? $this->denda : ($dataQuizUser->value < $this->kkm ? $this->denda : 0)
            ]);
        }
        return collect($result);
    }

    public function headings(): array
    {
        return [
            'nama',
            'benar',
            'salah',
            'total pertanyaan',
            'nilai',
            'potongan',
        ];
    }

    public function map($row): array
    {
        return [
            $row['full_name'],
            $row['correct_answer'],
            $row['wrong_answer'],
            $row['total_question'],
            $row['value'],
            $row['potongan'],
        ];
    }
}
