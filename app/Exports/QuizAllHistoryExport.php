<?php

namespace App\Exports;

use App\Models\QuizUserAnswer;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class QuizAllHistoryExport implements FromCollection, WithHeadings, WithMapping
{
    protected int $quizId;

    function __construct(int $quizId)
    {
        $this->quizId = $quizId;
    }

    public function collection()
    {
        return QuizUserAnswer::with(['user' => function ($q) {
            $q->withTrashed();
        }, 'option' => function ($q) {
            $q->withTrashed();
        }, 'question.options' => function ($q) {
            $q->withTrashed();
        }])->withTrashed()
            ->where('quiz_id', $this->quizId)
            ->where('value', 0)
            ->get()
            ->sortBy('user.full_name');
    }

    public function headings(): array
    {
        return [
            'nama',
            'hasil',
            'pertanyaan',
            'jawaban user',
            'jawaban yang benar',
        ];
    }

    public function map($row): array
    {
        return [
            $row->user->full_name ?? 'Nonactive user',
            $row->value ? 'Benar' : 'Salah',
            $row->question->question,
            $row->option->content,
            $row->question->options->where('is_true', 1)->first()->content,
        ];
    }
}
