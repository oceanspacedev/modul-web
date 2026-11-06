<?php

namespace App\Models;

use App\Models\Concerns\HasQuizImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Soal pilihan ganda (A–E); bank soal yang sama dipakai untuk pretest dan posttest.
 * Pertanyaan dan tiap pilihan boleh bergambar (lihat HasQuizImages).
 */
class LearningModuleQuestion extends Model
{
    use HasQuizImages;

    public const OPTION_LETTERS = ['a', 'b', 'c', 'd', 'e'];

    protected $guarded = ['id'];

    public function module(): BelongsTo
    {
        return $this->belongsTo(LearningModule::class, 'learning_module_id');
    }
}
