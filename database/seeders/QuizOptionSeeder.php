<?php

namespace Database\Seeders;

use App\Models\QuizOption;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class QuizOptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        QuizOption::insert([
            [
               'quiz_question_id' => 1,
               'content' => 'satu hari sekali',
               'is_true' => true,
            ],
            [
                'quiz_question_id' => 1,
                'content' => 'dua hari sekali',
                'is_true' => false,
            ],
            [
                'quiz_question_id' => 1,
                'content' => 'satu minggu sekali',
                'is_true' => false,
            ], [
                'quiz_question_id' => 1,
                'content' => 'tiga hari sekali',
                'is_true' => false,
            ],
            [
                'quiz_question_id' => 2,
                'content' => 'satu hari sekal',
                'is_true' => false,
            ],
            [
                'quiz_question_id' => 2,
                'content' => 'dua hari sekali',
                'is_true' => true,
            ],
            [
                'quiz_question_id' => 2,
                'content' => 'satu minggu sekali',
                'is_true' => false,
            ],
            [
                'quiz_question_id' => 2,
                'content' => 'tiga hari sekali',
                'is_true' => false,
            ],
        ]);
    }
}
