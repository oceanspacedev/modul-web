<?php

namespace App\Models;

use App\Models\Concerns\HasQuizImages;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Soal kuis pelatihan: pilihan ganda (A–F) atau essay. Pertanyaan dan tiap pilihan boleh bergambar.
 */
class TrainingQuestion extends Model
{
    use HasFactory;
    use HasQuizImages;

    public const OPTION_LETTERS = ['a', 'b', 'c', 'd', 'e', 'f'];

    protected $guarded = ['id'];

    public function training()
    {
        return $this->belongsTo(Training::class, 'training_id');
    }
}
