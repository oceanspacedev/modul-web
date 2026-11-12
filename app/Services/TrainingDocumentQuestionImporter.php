<?php

namespace App\Services;

use App\Models\LearningModule;
use App\Models\Training;
use App\Models\TrainingQuestion;
use App\Support\QuizImage;
use Illuminate\Support\Facades\DB;
use Throwable;

class TrainingDocumentQuestionImporter
{
    public function import(Training $training, LearningModule $quiz): int
    {
        $usedIn = match (true) {
            $quiz->has_pretest && $quiz->has_posttest => TrainingQuestion::USED_IN_BOTH,
            $quiz->has_pretest => Training::PHASE_PRETEST,
            default => Training::PHASE_POSTTEST,
        };

        $copiedImages = [];
        $copyImage = function (?string $source) use (&$copiedImages): ?string {
            if (blank($source)) {
                return null;
            }

            $path = QuizImage::copy($source, 'quiz_document_id');
            $copiedImages[] = $path;

            return $path;
        };

        try {
            return DB::transaction(function () use ($training, $quiz, $usedIn, $copyImage) {
                // Serialize concurrent imports for this training, including a double submit of the same document.
                $lockedTraining = Training::query()->lockForUpdate()->findOrFail($training->getKey());
                $importedIds = $lockedTraining->questions()
                    ->whereNotNull('source_learning_module_question_id')
                    ->select('source_learning_module_question_id');
                $questions = $quiz->questions()->whereNotIn('id', $importedIds)->get();

                foreach ($questions as $question) {
                    // Each quiz owns its images and can edit or delete them independently.
                    $optionImages = array_filter(array_map($copyImage, $question->option_images ?? []));

                    $lockedTraining->questions()->create([
                        ...$question->only(['question', 'option_a', 'option_b', 'option_c', 'option_d', 'option_e', 'correct_answer', 'explanation']),
                        'type' => 'multiple_choice',
                        'used_in' => $usedIn,
                        'source_learning_module_question_id' => $question->id,
                        'question_image' => $copyImage($question->question_image),
                        'option_images' => $optionImages ?: null,
                    ]);
                }

                return $questions->count();
            });
        } catch (Throwable $exception) {
            QuizImage::delete(...$copiedImages);

            throw $exception;
        }
    }
}
