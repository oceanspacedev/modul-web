<?php

use App\Support\TrainingEvaluationForm;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pertanyaan form evaluasi kini milik tiap pelatihan (bisa ditambah, diubah, dihapus, diurutkan), bukan daftar tetap
 * di kode. Jawaban disimpan per id pertanyaan di training_evaluations.answers; jawaban lama (kunci tetap + kolom
 * comment) dipindahkan ke pertanyaan bawaan pelatihannya.
 */
return new class extends Migration
{
    /** Kunci jawaban lama, sesuai urutan pertanyaan skala di template bawaan. */
    private const LEGACY_KEYS = [
        'trainer_clarity', 'trainer_mastery', 'trainer_engagement', 'trainer_overall',
        'material_useful', 'meets_expectation', 'well_organized', 'duration_fit', 'overall',
    ];

    public function up(): void
    {
        Schema::create('training_evaluation_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained('trainings')->cascadeOnDelete();
            $table->string('section')->nullable();
            $table->string('type', 20);
            $table->text('question');
            $table->string('low_label')->nullable();
            $table->string('high_label')->nullable();
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['training_id', 'position']);
        });

        Schema::table('training_evaluations', function (Blueprint $table) {
            $table->json('answers')->nullable()->after('user_id');
        });

        $this->moveLegacyAnswers();

        Schema::table('training_evaluations', function (Blueprint $table) {
            $table->dropColumn(['ratings', 'comment']);
        });
    }

    /**
     * Pelatihan yang sudah memakai evaluasi mendapat pertanyaan bawaan, lalu jawaban lamanya dikunci ulang ke id
     * pertanyaan tersebut.
     */
    private function moveLegacyAnswers(): void
    {
        $trainingIds = DB::table('trainings')->where('has_evaluation', true)->pluck('id')
            ->merge(DB::table('training_evaluations')->distinct()->pluck('training_id'))
            ->unique();

        foreach ($trainingIds as $trainingId) {
            $scaleIds = [];
            $commentId = null;

            foreach (TrainingEvaluationForm::defaultQuestions() as $position => $question) {
                $id = DB::table('training_evaluation_questions')->insertGetId([
                    ...$question,
                    'options' => null,
                    'training_id' => $trainingId,
                    'position' => $position + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($question['type'] === 'scale') {
                    $scaleIds[] = $id;
                } else {
                    $commentId = $id;
                }
            }

            foreach (DB::table('training_evaluations')->where('training_id', $trainingId)->get(['id', 'ratings', 'comment']) as $evaluation) {
                $ratings = json_decode((string) $evaluation->ratings, true) ?: [];
                $answers = [];

                foreach (self::LEGACY_KEYS as $index => $key) {
                    if (isset($ratings[$key], $scaleIds[$index])) {
                        $answers[$scaleIds[$index]] = (int) $ratings[$key];
                    }
                }
                if ($commentId && filled($evaluation->comment)) {
                    $answers[$commentId] = $evaluation->comment;
                }

                DB::table('training_evaluations')->where('id', $evaluation->id)->update(['answers' => json_encode($answers)]);
            }
        }
    }

    /**
     * Kolom lama dikembalikan kosong: jawaban per pertanyaan tidak bisa dipetakan balik ke kunci tetap.
     */
    public function down(): void
    {
        Schema::table('training_evaluations', function (Blueprint $table) {
            $table->json('ratings')->nullable()->after('user_id');
            $table->text('comment')->nullable()->after('ratings');
        });

        Schema::table('training_evaluations', function (Blueprint $table) {
            $table->dropColumn('answers');
        });

        Schema::dropIfExists('training_evaluation_questions');
    }
};
