<?php

namespace Database\Seeders;

use App\Models\QuizQuestion;
use Illuminate\Database\Seeder;

class QuizQuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        QuizQuestion::insert([
            [
                'document_id' => 1,
                'question' => 'berapa hari sekali RO dan Promotor melakukan proses stock opname handphone?',
            ],
            [
                'document_id' => 1,
                'question' => 'berapa hari sekali RO dan Promotor melakukan proses stock opname aksesori?',
            ],
        ]);
    }
}
